<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit\Listeners;

use App\Importing\Repositories\ImportStatRepository;
use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Enums\ImportSource;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Listeners\RecordsImportStat;
use App\Importing\Enums\ReasonForSkippingBookmark;
use App\Importing\Collections\TagsCollection;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RecordsImportStatTest extends TestCase
{
    #[Test]
    public function recordStat(): void
    {
        $bookmark = new Bookmark('', new TagsCollection([]), 22);
        $cache = new Repository(new ArrayStore());

        $listener = new RecordsImportStat(new ImportStatRepository($cache, 84600));
        $listener->importsStarted(new ImportBookmarkRequestData('key', ImportSource::CHROME, 33, []));

        $this->assertEquals($listener->getReport(), new ImportStats());

        $listener->importStarted($bookmark);
        $listener->bookmarkImported($bookmark);

        $listener->importStarted($bookmark);
        $listener->bookmarkNotProcessed($bookmark);

        $listener->importStarted($bookmark);
        $listener->bookmarkSkipped(ReasonForSkippingBookmark::INVALID_TAG);

        $listener->importStarted($bookmark);
        $listener->importFailed($bookmark, ImportBookmarksStatus::FAILED_DUE_TO_INVALID_BOOKMARK_URL);

        $listener->importStarted($bookmark);
        $listener->bookmarkNotProcessed($bookmark);
        $listener->importStarted($bookmark);
        $listener->bookmarkNotProcessed($bookmark);
        $listener->importStarted($bookmark);
        $listener->bookmarkNotProcessed($bookmark);

        $stat = $listener->getReport();
        $this->assertEquals(1, $stat->totalImported);
        $this->assertEquals(1, $stat->totalSkipped);
        $this->assertEquals(7, $stat->totalFound);
        $this->assertEquals(4, $stat->totalUnProcessed);
        $this->assertEquals(1, $stat->totalFailed);
    }
}
