<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Pocket;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Importers\Pocket\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Pocket\Importer as Importer;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use Carbon\Carbon;
use Database\Factories\TagFactory;
use Tests\Unit\Importers\MockFilesystem;

class ImporterTest extends TestCase
{
    use WithFaker, MockFilesystem;

    public function testWillThrowExceptionIfFileDoesNotExists(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    protected function getImporter(): Importer
    {
        return app(Importer::class);
    }

    public function testWillClearDataAfterImport(): void
    {
        $DOMParser = $this->getMockBuilder(DOMParserInterface::class)->getMock();

        $this->mockFilesystem(function (MockObject $mock) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn('');
            $mock->expects($this->once())->method('delete');
        });

        $DOMParser->expects($this->once())->method('parse')->willReturn(new ArrayIterator());

        $this->swap(DOMParserInterface::class, $DOMParser);

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillUseBookmarkTagsByDefault(): void
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['2017', 'bots', 'telegram'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillUseOnlyUniqueTags(): void
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
                        <li><a href="https://cai.tools.sap/blog/top-telegram-bots-2017/" time_added="1627725769" tags="2017,bots,bots,BOTS,BoTs,telegram">Top 8 Telegram Bots in 2017 | SAP Conversational AI Blog</a></li>
                    </ul>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['2017', 'bots', 'telegram'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillNotUseBookmarkTagsWhenIndicated(): void
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEmpty($bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, ['ignore_tags' => true]);
    }

    public function test_will_not_use_bookmark_tags_when_tags_count_is_greater_than_15(): void
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEmpty($bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillNotUseIncompatibleBookmarkTags(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $tags = implode(',', [
            Str::random(23), ' ', 'bot'
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['bot'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    private function mockServiceClass(Closure $mock): void
    {
        $service = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $mock($service);

        $this->swap(CreateBookmarkService::class, $service);
    }

    public function testWillUseBookmarkDateByDefault(): void
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(Carbon::parse($bookmark['createdOn'])->year, 2021);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillNotUseBookmarkDateWhenIndicated(): void
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertTrue(Carbon::parse($bookmark['createdOn'])->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, ['use_timestamp' => false]);
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertTrue(Carbon::parse($bookmark['createdOn'])->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->never())->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillStoreBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/Imports/pocketExportFile.html'))
            );
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(11))->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = 33;

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

        $this->mockServiceClass(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($userID) {
                    $this->assertEquals($bookmark, [
                        'url'       => new Url('https://cai.tools.sap/blog/top-telegram-bots-2017/'),
                        'tags'      => [],
                        'createdOn' => '2021-07-31 10:02:49',
                        'userID'    => $userID,
                    ]);

                    return $bookmark;
                });
        });

        $this->getImporter()->import($userID, $this->faker->uuid, []);
    }
}
