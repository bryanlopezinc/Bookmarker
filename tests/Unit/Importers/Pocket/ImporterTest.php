<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Pocket;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use App\ValueObjects\Uuid;
use Illuminate\Support\Str;
use App\ValueObjects\UserID;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use App\DataTransferObjects\Bookmark;
use App\Importers\FilesystemInterface;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Contracts\CreateBookmarkRepositoryInterface;
use App\Importers\Pocket\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Pocket\Importer as Importer;
use App\ValueObjects\Tag;
use Database\Factories\TagFactory;

class ImporterTest extends TestCase
{
    use WithFaker;

    public function testWillThrowExceptionIfFileDoesNotExists(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->getImporter()->import(new UserID(22), Uuid::generate(), []);
    }

    protected function getImporter(): Importer
    {
        return app(Importer::class);
    }

    public function testWillClearDataAfterImport(): void
    {
        $requestID = Uuid::generate();
        $DOMParser = $this->getMockBuilder(DOMParserInterface::class)->getMock();

        $this->mockFilesystem(function (MockObject $mock) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn('');
            $mock->expects($this->once())->method('delete');
        });

        $DOMParser->expects($this->once())->method('parse')->willReturn(new ArrayIterator());

        $this->swap(DOMParserInterface::class, $DOMParser);

        $this->getImporter()->import(new UserID(120), $requestID, []);
    }

    private function mockFilesystem(Closure $mock): void
    {
        $filesystem = $this->getMockBuilder(FilesystemInterface::class)->getMock();

        $mock($filesystem);

        $this->swap(FilesystemInterface::class, $filesystem);
    }

    public function testWillAttachPocketBookmarkTagsToBookmarkByDefault(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['2017', 'bots', 'telegram'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(200), Uuid::generate(), []);
    }

    public function testWillAttachOnlyUniquePocketBookmarkTagsToBookmark(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['2017', 'bots', 'telegram'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(330), Uuid::generate(), []);
    }

    public function testWillNotAttachPocketBookmarkTagsToBookmarkWhenIndicated(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->tags->isEmpty());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(7), Uuid::generate(), ['ignore_tags' => true]);
    }

    public function test_will_not_attach_pocket_bookmark_tags_to_bookmark_when_pocket_bookmarks_tags_count_is_greater_than_15(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $tags = TagFactory::new()->count(16)->make()->pluck('name')->implode(',');

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="$tags">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->tags->isEmpty());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(45), Uuid::generate(), []);
    }

    public function testWillNotAttachIncompatiblePocketBookmarkTagsToBookmark(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $tags = implode(',', [
            Str::random(Tag::MAX_LENGTH + 1),
            ' ',
            '@#tag',
            'bot'
        ]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="$tags">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals('bot', $bookmark->tags->toStringCollection()->sole());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(950), Uuid::generate(), []);
    }

    private function mockRepository(Closure $mock): void
    {
        $repository = $this->getMockBuilder(CreateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(CreateBookmarkRepositoryInterface::class, $repository);
    }

    public function testWillUsePocketBookmarkDateByDefault(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->timeCreated->year, 2021);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(210), Uuid::generate(), []);
    }

    public function testWillNotUsePocketBookmarkDateWhenIndicated(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->timeCreated->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(320), Uuid::generate(), ['use_timestamp' => false]);
    }

    public function testWillUseDefaultDateWhenDateIsInvalid(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="3030303003303030303030303030303030303003" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->timeCreated->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(2144), Uuid::generate(), []);
    }

    public function testWillNotSaveBookmarkIfUrlIsInvalid(): void
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="<sricpt>alert('crsf')</script>" time_added="1627725769" tags="2017,bots,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->never())->method('create');
        });

        $this->getImporter()->import(new UserID(4344), Uuid::generate(), []);
    }

    public function testWillStoreBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/imports/pocketExportFile.html'))
            );
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(11))->method('create')->willReturn(new Bookmark([]));
        });

        $this->getImporter()->import(new UserID(2302), Uuid::generate(), []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = new UserID(rand(10, PHP_INT_MAX));

        $html = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Pocket Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ul>
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($userID) {
                    $this->assertEquals("https://cai.tools.sap/blog/top-telegram-bots-2017/", $bookmark->url->toString());
                    $this->assertEquals(1627725769, $bookmark->timeCreated->timestamp);
                    $this->assertTrue($bookmark->description->isEmpty());
                    $this->assertFalse($bookmark->descriptionWasSetByUser);
                    $this->assertEquals('cai.tools.sap', $bookmark->fromWebSite->domainName->value);
                    $this->assertFalse($bookmark->hasCustomTitle);
                    $this->assertFalse($bookmark->hasThumbnailUrl);
                    $this->assertTrue($bookmark->ownerId->equals($userID));
                    $this->assertFalse($bookmark->hasThumbnailUrl);
                    $this->assertTrue($bookmark->tags->isEmpty());
                    return $bookmark;
                });
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);
    }
}
