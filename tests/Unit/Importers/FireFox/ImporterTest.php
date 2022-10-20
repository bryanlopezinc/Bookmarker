<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\FireFox;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use App\ValueObjects\Uuid;
use Illuminate\Support\Str;
use App\ValueObjects\UserID;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use App\DataTransferObjects\Bookmark;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Contracts\CreateBookmarkRepositoryInterface;
use App\Importers\FireFox\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\FireFox\Importer as Importer;
use App\ValueObjects\Tag;
use Database\Factories\TagFactory;
use Tests\Unit\Importers\MockFilesystem;

class ImporterTest extends TestCase
{
    use WithFaker, MockFilesystem;

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

    public function testWillUseBookmarkTagsByDefault(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                    LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['fifa', 'gaming'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(200), Uuid::generate(), []);
    }

    public function testWillUseOnlyUniqueTags(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
             <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming,fifa">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['fifa', 'gaming'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(330), Uuid::generate(), []);
    }

    public function testWillNotUseBookmarkTagsWhenIndicated(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
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

    public function test_will_not_use_bookmark_tags_when_tags_count_is_greater_than_15(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $tags = TagFactory::new()->count(16)->make()->pluck('name')->implode(',');

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="$tags">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
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

    public function testWillNotUseIncompatibleBookmarkTags(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $tags = implode(',', [
            Str::random(Tag::MAX_LENGTH + 1), ' ', 'bot'
        ]);

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="$tags">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
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

    public function testWillUseBookmarkDateByDefault(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->timeCreated->year, 2022);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(210), Uuid::generate(), []);
    }

    public function testWillNotUseBookmarkDateWhenIndicated(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
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
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="343443434344343434343343443434343434343434433434"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
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
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="<script>alert(gone)</script>" ADD_DATE="1627725769"
                                        LAST_MODIFIED="1666294035">ubuntu - Official Image | Docker Hub</A>
                        </DL>
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

        $expectedUrls = [
            'https://hub.docker.com/_/postgres',
            'https://www.rottentomatoes.com/m/vhs99',
            'https://www.macrumors.com/',
            'https://hub.docker.com/_/mongo',
            'https://hub.docker.com/_/ubuntu',
            'https://store.playstation.com/en-us/concept/10004336'
        ];

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/imports/firefox.html'))
            );
        });

        $this->mockRepository(function (MockObject $repository) use ($expectedUrls) {
            $repository->expects($this->exactly(6))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($expectedUrls) {
                    $this->assertContains($bookmark->url->toString(), $expectedUrls);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(2302), Uuid::generate(), []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = new UserID(rand(10, PHP_INT_MAX));

        $html = <<<HTML
                        <!DOCTYPE NETSCAPE-Bookmark-file-1>
                        <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
                        <meta http-equiv="Content-Security-Policy"
                            content="default-src 'self'; script-src 'none'; img-src data: *; object-src 'none'">
                        </meta>
                        <DL>
                                <DT><A HREF="https://store.playstation.com/en-us/concept/10004336" ADD_DATE="1666294452"
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($userID) {
                    $this->assertEquals("https://store.playstation.com/en-us/concept/10004336", $bookmark->url->toString());
                    $this->assertEquals(1666294452, $bookmark->timeCreated->timestamp);
                    $this->assertTrue($bookmark->description->isEmpty());
                    $this->assertFalse($bookmark->descriptionWasSetByUser);
                    $this->assertEquals('store.playstation.com', $bookmark->source->domainName->value);
                    $this->assertFalse($bookmark->hasCustomTitle);
                    $this->assertFalse($bookmark->hasThumbnailUrl);
                    $this->assertTrue($bookmark->ownerId->equals($userID));
                    $this->assertFalse($bookmark->hasThumbnailUrl);
                    $this->assertFalse($bookmark->tags->isEmpty());
                    return $bookmark;
                });
        });

        $this->getImporter()->import($userID, Uuid::generate(), []);
    }
}
