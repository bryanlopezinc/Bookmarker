<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Chrome;

use App\Contracts\CreateBookmarkRepositoryInterface;
use App\DataTransferObjects\Bookmark;
use App\Importers\Chrome\Importer as Importer;
use App\Importers\Chrome\DOMParserInterface;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;
use ArrayIterator;
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

    public function testWillThrowExceptionIfFileDoesNotExists(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
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

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
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

        $this->mockRepository(function (MockObject $repository) use ($tag) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($tag) {
                    $this->assertEquals($bookmark->tags->toStringCollection()->sole(), $tag);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), ['tags' => [$tag]]);
    }

    private function mockRepository(Closure $mock): void
    {
        $repository = $this->getMockBuilder(CreateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(CreateBookmarkRepositoryInterface::class, $repository);
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

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->timeCreated->year, 2017);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
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

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->timeCreated->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), ['use_timestamp' => false]);
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

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertTrue($bookmark->timeCreated->isToday());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
    }

    public function testWillNotSaveBookmarkIfUrlIsInvalid(): void
    {
        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DL>
                    <DT><A HREF="<sricpt>alert('crsf')</script>" ADD_DATE="1505762363">htaccess - Ultimate</A>
            </DL>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->never())->method('create');
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
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

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(111))->method('create')->willReturn(new Bookmark([]));
        });

        $this->getImporter()->import(new UserID(21), Uuid::generate(), []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = new UserID(5430);

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

        $this->mockRepository(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($userID) {
                    $this->assertEquals("https://www.askapache.com/htaccess/", $bookmark->url->toString());
                    $this->assertEquals(1505762363, $bookmark->timeCreated->timestamp);
                    $this->assertTrue($bookmark->description->isEmpty());
                    $this->assertFalse($bookmark->descriptionWasSetByUser);
                    $this->assertEquals('askapache.com', $bookmark->source->domainName->value);
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
