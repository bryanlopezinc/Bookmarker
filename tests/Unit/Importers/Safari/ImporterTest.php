<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Safari;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Importers\Safari\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Safari\Importer;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['apple'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, ['tags' => ['apple']]);
    }

    private function mockServiceClass(Closure $mock): void
    {
        $service = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $mock($service);

        $this->swap(CreateBookmarkService::class, $service);
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->never())->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testStoreBookmarks(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $this->mockFilesystem(function (MockObject $filesystem) {
            $filesystem->expects($this->once())->method('exists')->willReturn(true);
            $filesystem->expects($this->once())->method('get')->willReturn(
                file_get_contents(base_path('tests/stubs/Imports/SafariExportFile.html'))
            );
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(32))->method('fromArray');
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }

    public function testWillSaveCorrectData(): void
    {
        Bus::fake([UpdateBookmarkWithHttpResponse::class]);

        $userID = 21;

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

        $this->travelTo($createdOn = now());

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockServiceClass(function (MockObject $repository) use ($userID, $createdOn) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($userID, $createdOn) {
                    $this->assertEquals($bookmark, [
                        'url'       => new Url('http://www.apple.com/'),
                        'tags'      => [],
                        'createdOn' => (string) $createdOn,
                        'userID'    => $userID,
                    ]);

                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, []);
    }
}
