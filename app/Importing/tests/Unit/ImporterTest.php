<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData as ImportData;
use App\Importing\Enums\ImportSource;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\Contracts\BookmarkImportedListenerInterface;
use App\Importing\Contracts\ImportFailedListenerInterface;
use App\Importing\Importer;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use App\Importing\Filesystem;
use App\Importing\HtmlFileIterator;
use App\Importing\Listeners\RecordsImportStat;
use App\Importing\EventDispatcher;
use App\Importing\Enums\ImportBookmarksStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use Tests\TestCase;

class ImporterTest extends TestCase
{
    #[Test]
    public function willThrowExceptionWhenFileDoesNotExists(): void
    {
        $this->expectException(FileNotFoundException::class);

        $filesystem = $this->filesystemMock(function (MockObject $mock) {
            $mock->method('exists')->willReturn(false);
        });

        $importer = $this->getImporterInstance(filesystem: $filesystem);

        $importer->import(new ImportData('foo', ImportSource::CHROME, 22, []));
    }

    public function getImporterInstance($filesystem, $listener = null, $iterator = new HtmlFileIterator()): Importer
    {
        $recordsImportStat = new RecordsImportStat(
            new ImportStatRepository(new Repository(new ArrayStore()), 84600)
        );

        $eventDispatcher = new EventDispatcher($recordsImportStat);

        if ($listener) {
            foreach (Arr::wrap($listener) as $value) {
                $eventDispatcher->addListener($value);
            }
        }

        return new Importer($iterator, $filesystem, $eventDispatcher);
    }

    private function filesystemMock(\Closure $mock)
    {
        $filesystem = $this->getMockBuilder(FilesystemContract::class)->getMock();

        $mock($filesystem);

        return new Filesystem($filesystem);
    }

    #[Test]
    public function import(): void
    {
        $filesystem = $this->filesystemMock(function (MockObject $mock) {
            $mock->expects($this->any())->method('exists')->willReturn(true);
            $mock->expects($this->exactly(5))
                ->method('get')
                ->willReturn(
                    $this->getViewInstance()->render(),
                    $this->getViewInstance('firefox')->with('bookmarks', [['tags' => '']])->render(),
                    $this->getViewInstance('instapaper')->with('bookmarks', [['url' => 'https://symfony.com/']])->render(),
                    $this->getViewInstance('pocket')->with('bookmarks', [['tags' => '']])->render(),
                    $this->getViewInstance('safari')->with('bookmarks', [['url' => 'https://www.apple.com/']])->render(),
                );
        });

        $listener = $this->getMockBuilder(BookmarkImportedListenerInterface::class)->getMock();

        $listener->expects($this->exactly(5))
            ->method('bookmarkImported')
            ->willReturnOnConsecutiveCalls(
                new ReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->tags->all(), []);
                    $this->assertEquals($bookmark->url, 'https://www.askapache.com/htaccess/');
                }),
                new ReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->tags->all(), []);
                    $this->assertEquals($bookmark->url, 'https://www.rottentomatoes.com/m/vhs99');
                }),
                new ReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->tags->all(), []);
                    $this->assertEquals($bookmark->url, 'https://symfony.com/');
                }),
                new ReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->tags->all(), []);
                    $this->assertEquals($bookmark->url, 'https://www.sitepoint.com/build-restful-apis-best-practices/');
                }),
                new ReturnCallback(function (Bookmark $bookmark) {
                    $this->assertEquals($bookmark->tags->all(), []);
                    $this->assertEquals($bookmark->url, 'https://www.apple.com/');
                }),
            );

        $importer = $this->getImporterInstance(listener: $listener, filesystem: $filesystem);
        $importData = new ImportData('foo', ImportSource::CHROME, 22, []);

        $importer->import($importData);
        $importer->import($importData->setSource(ImportSource::FIREFOX));
        $importer->import($importData->setSource(ImportSource::INSTAPAPER));
        $importer->import($importData->setSource(ImportSource::POCKET));
        $importer->import($importData->setSource(ImportSource::SAFARI));
    }

    private function getViewInstance(string $file = 'chromeExportFile')
    {
        return View::file(__DIR__ . "/../stubs/{$file}.blade.php")
            ->with('includeBookmarksBar', false)
            ->with('includeBookmarksInPersonalToolBar', false)
            ->with('bookmarks', [['url' => 'https://www.askapache.com/htaccess/']]);
    }

    #[Test]
    public function whenUrlIsInvalid(): void
    {
        $filesystem = $this->filesystemMock(function (MockObject $mock) {
            $mock->expects($this->any())->method('exists')->willReturn(true);
            $mock->expects($this->exactly(5))
                ->method('get')
                ->willReturn(
                    $this->getViewInstance()->with('bookmarks', [['url' => 'bar']])->render(),
                    $this->getViewInstance('firefox')->with('bookmarks', [['tags' => '', 'url' => 'foo']])->render(),
                    $this->getViewInstance('instapaper')->with('bookmarks', [['url' => 'bar']])->render(),
                    $this->getViewInstance('pocket')->with('bookmarks', [['tags' => '', 'url' => 'foo']])->render(),
                    $this->getViewInstance('safari')->with('bookmarks', [['url' => 'bar']])->render(),
                );
        });

        $listener = $this->getMockBuilder(ImportFailedListenerInterface::class)->getMock();
        $bookmarkImportedListener = $this->getMockBuilder(BookmarkImportedListenerInterface::class)->getMock();

        $listener->expects($this->exactly(5))
            ->method('importFailed')
            ->willReturnCallback(function (Bookmark $bookmark, ImportBookmarksStatus $reason) {
                $this->assertEquals($reason, $reason::FAILED_DUE_TO_INVALID_BOOKMARK_URL);
            });

        $bookmarkImportedListener->expects($this->never())->method('bookmarkImported');

        $importer = $this->getImporterInstance(listener: [$listener, $bookmarkImportedListener], filesystem: $filesystem);
        $importData = new ImportData('foo', ImportSource::CHROME, 22, []);

        $importer->import($importData);
        $importer->import($importData->setSource(ImportSource::FIREFOX));
        $importer->import($importData->setSource(ImportSource::INSTAPAPER));
        $importer->import($importData->setSource(ImportSource::POCKET));
        $importer->import($importData->setSource(ImportSource::SAFARI));
    }

    #[Test]
    public function willReturnCorrectResult(): void
    {
        $filesystem = $this->filesystemMock(function (MockObject $mock) {
            $mock->expects($this->any())->method('exists')->willReturn(true);
            $mock->expects($this->once())
                ->method('get')
                ->willReturn(
                    $this->getViewInstance()->with('bookmarks', [
                        ['url' => 'bar', 'tags' => str_repeat('F', 16)],
                        ['url' => 'foo'],
                        ['url' => 'https://www.apple.com/']
                    ])->render(),
                );
        });

        $importer = $this->getImporterInstance(filesystem: $filesystem);
        $importData = new ImportData('foo', ImportSource::FIREFOX, 22, ['invalid_bookmark_tag' => 'fail_import']);

        $result = $importer->import($importData)->statistics;
        $this->assertEquals($result->totalFailed, 1);
        $this->assertEquals($result->totalFound, 3);
        $this->assertEquals($result->totalImported, 0);
        $this->assertEquals($result->totalSkipped, 0);
        $this->assertEquals($result->totalUnProcessed, 2);
    }
}
