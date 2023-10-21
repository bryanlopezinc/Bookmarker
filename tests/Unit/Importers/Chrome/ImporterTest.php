<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Chrome;

use App\Importers\Chrome\Importer as Importer;
use App\Importers\Chrome\DOMParserInterface;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use App\ValueObjects\UserID;
use ArrayIterator;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\Unit\Importers\MockFilesystem;

class ImporterTest extends TestCase
{
    use WithFaker, MockFilesystem;

    public function testWillThrowExceptionWhenFileDoesNotExists(): void
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

    public function testWillAttachTagsToBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $url = $this->faker->url;
        $tag = $this->faker->word;

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
             <DT><A HREF=$url ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockServiceClass(function (MockObject $repository) use ($tag) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($tag) {
                    $this->assertEquals($bookmark['tags'], [$tag]);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, ['tags' => [$tag]]);
    }

    private function mockServiceClass(Closure $mock): void
    {
        $service = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $mock($service);

        $this->swap(CreateBookmarkService::class, $service);
    }

    public function testWillUseChromeBookmarkDateByDefault(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
             <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
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
                    $this->assertEquals(Carbon::parse($bookmark['createdOn'])->year, 2017);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillNotUseChromeBookmarkDateWhenIndicated(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
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
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
             <DT><A HREF='https://laravel.com/docs/9.x/requests#files' ADD_DATE="3030303003303030303030303030303030303003">htaccess - Ultimate</A>
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
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
             <DT><A HREF="<script>alert('hacked')</script>" ADD_DATE="1505762363">htaccess - Ultimate</A>
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

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/imports/chromeExportFile.html'))
            );
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(111))->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = 5430;

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                <DT><A HREF="https://www.askapache.com/htaccess/" ADD_DATE="1505762363">htaccess - Ultimate Apache .htaccess file Guide</A>
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
                        'url'       => new Url('https://www.askapache.com/htaccess/'),
                        'tags'      => [],
                        'createdOn' => '2017-09-18 19:19:23',
                        'userID'    => $userID,
                    ]);

                    return $bookmark;
                });
        });

        $this->getImporter()->import($userID, $this->faker->uuid, []);
    }
}
