<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Safari;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use App\ValueObjects\Uuid;
use App\ValueObjects\UserID;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use App\DataTransferObjects\Bookmark;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Contracts\CreateBookmarkRepositoryInterface;
use App\Importers\Safari\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Safari\Importer;
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

    public function testWillAttachTagsToBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DT><H3 FOLDED>Favourites</H3>
            <DL><p>
                <DT><A HREF="http://www.apple.com/">Apple</A>
            </DL><p>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['fromSafari', 'apple'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(200), Uuid::generate(), ['tags' => ['fromSafari', 'apple']]);
    }

    private function mockRepository(Closure $mock): void
    {
        $repository = $this->getMockBuilder(CreateBookmarkRepositoryInterface::class)->getMock();

        $mock($repository);

        $this->swap(CreateBookmarkRepositoryInterface::class, $repository);
    }

    public function testWillNotSaveBookmarkIfUrlIsInvalid(): void
    {
        $html = <<<HTML
            <!DOCTYPE NETSCAPE-Bookmark-file-1>
            <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DT><H3 FOLDED>Favourites</H3>
            <DL><p>
                <DT><A HREF="<sricpt>alert('crsf')</script>">Apple</A>
            </DL><p>
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

    public function testStoreBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/imports/SafariExportFile.html'))
            );
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(32))->method('create')->willReturn(new Bookmark([]));
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
            <TITLE>Bookmarks</TITLE>
            <H1>Bookmarks</H1>
            <DT><H3 FOLDED>Favourites</H3>
            <DL><p>
                <DT><A HREF="http://www.apple.com/">Apple</A>
            </DL><p>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($userID) {
                    $this->assertEquals("http://www.apple.com/", $bookmark->url->toString());
                    $this->assertTrue($bookmark->timeCreated->isSameMinute());
                    $this->assertTrue($bookmark->description->isEmpty());
                    $this->assertFalse($bookmark->descriptionWasSetByUser);
                    $this->assertEquals('apple.com', $bookmark->source->domainName->value);
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
