<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Instapaper;

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
use App\Importers\Instapaper\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Instapaper\Importer;
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
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Instapaper: Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ol>
                        <li><a href="https://symfony.com/">Symfony, High Performance PHP Framework for Web Development</a>
                    </ol>
                </body>
            </html>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals(['fromInstapaper', 'symfony'], $bookmark->tags->toStringCollection()->all());
                    return $bookmark;
                });
        });

        $this->getImporter()->import(new UserID(200), Uuid::generate(), ['tags' => ['fromInstapaper', 'symfony']]);
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
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Instapaper: Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ol>
                        <li><a href="<script>alert('crsf')</script>">Symfony, High Performance PHP Framework for Web Development</a>
                    </ol>
                </body>
            </html>
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
                file_get_contents(base_path('tests/stubs/imports/instapaper.html'))
            );
        });

        $this->mockRepository(function (MockObject $repository) {
            $repository->expects($this->exactly(4))->method('create')->willReturn(new Bookmark([]));
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
                    <title>Instapaper: Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ol>
                        <li><a href="https://www.goal.com/en">Football News, Live Scores, Results &amp; Transfers | Goal.com</a>
                    </ol>
                </body>
            </html>
        HTML;

        $this->mockFilesystem(function (MockObject $mock) use ($html) {
            $mock->expects($this->once())->method('exists')->willReturn(true);
            $mock->expects($this->once())->method('get')->willReturn($html);
        });

        $this->mockRepository(function (MockObject $repository) use ($userID) {
            $repository->expects($this->exactly(1))
                ->method('create')
                ->willReturnCallback(function (Bookmark $bookmark) use ($userID) {
                    $this->assertEquals("https://www.goal.com/en", $bookmark->url->toString());
                    $this->assertTrue($bookmark->timeCreated->isSameMinute());
                    $this->assertTrue($bookmark->description->isEmpty());
                    $this->assertFalse($bookmark->descriptionWasSetByUser);
                    $this->assertEquals('goal.com', $bookmark->source->domainName->value);
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
