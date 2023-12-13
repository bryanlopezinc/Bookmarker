<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\FireFox;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Importers\FireFox\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\FireFox\Importer as Importer;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use Carbon\Carbon;
use Database\Factories\TagFactory;
use Tests\Unit\Importers\MockFilesystem;

class ImporterTest extends TestCase
{
    use WithFaker;
    use MockFilesystem;

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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['fifa', 'gaming'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
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
                                        LAST_MODIFIED="1666294452" TAGS="fifa,gaming,fifa,FIFA,fIFA,GAMING">EA SPORTS™ FIFA 23 Standard Edition PS5™<A>
                        </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['fifa', 'gaming'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(Carbon::parse($bookmark['createdOn'])->year, 2022);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->never())->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
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
                file_get_contents(base_path('tests/stubs/Imports/firefox.html'))
            );
        });

        $this->mockServiceClass(function (MockObject $repository) use ($expectedUrls) {
            $repository->expects($this->exactly(6))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($expectedUrls) {
                    $this->assertContains($bookmark['url']->toString(), $expectedUrls);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = 32;

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

        $this->mockServiceClass(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($userID) {
                    $this->assertEquals($bookmark, [
                        'url'       => new Url('https://store.playstation.com/en-us/concept/10004336'),
                        'tags'      => ['fifa', 'gaming'],
                        'createdOn' => '2022-10-20 19:34:12',
                        'userID'    => $userID,
                    ]);

                    return $bookmark;
                });
        });

        $this->getImporter()->import($userID, $this->faker->uuid, []);
    }
}
