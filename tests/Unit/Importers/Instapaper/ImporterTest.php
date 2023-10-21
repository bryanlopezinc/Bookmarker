<?php

declare(strict_types=1);

namespace Tests\Unit\Importers\Instapaper;

use Closure;
use ArrayIterator;
use Tests\TestCase;
use App\Jobs\UpdateBookmarkWithHttpResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use App\Importers\Instapaper\DOMParserInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Importers\Instapaper\Importer;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use Carbon\Carbon;
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

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->once())
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) {
                    $this->assertEquals(['symfony'], $bookmark['tags']);
                    return $bookmark;
                });
        });

        $this->getImporter()->import(21, $this->faker->uuid, ['tags' => ['symfony']]);
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
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                    <title>Instapaper: Export</title>
                </head>
                <body>
                    <h1>Unread</h1>
                    <ol>
                        <li><a href="<script>alert('hacked')</script>">Symfony, High Performance PHP Framework for Web Development</a>
                    </ol>
                </body>
            </html>
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
                file_get_contents(base_path('tests/stubs/imports/instapaper.html'))
            );
        });

        $this->mockServiceClass(function (MockObject $repository) {
            $repository->expects($this->exactly(4))->method('fromArray');
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

        $this->travelTo($createdOn = now());

        $this->mockServiceClass(function (MockObject $repository) use ($userID, $createdOn) {
            $repository->expects($this->exactly(1))
                ->method('fromArray')
                ->willReturnCallback(function (array $bookmark) use ($userID, $createdOn) {
                    $this->assertEquals($bookmark, [
                        'url'       => new Url('https://www.goal.com/en'),
                        'tags'      => [],
                        'createdOn' => (string) $createdOn,
                        'userID'    => $userID,
                    ]);

                    return $bookmark;
                });
        });

        $this->getImporter()->import(33, $this->faker->uuid, []);
    }
}
