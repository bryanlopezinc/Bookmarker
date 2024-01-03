<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Listeners;

use App\Import\ImportBookmarkRequestData as ImportData;
use App\Enums\ImportSource;
use App\Import\Bookmark;
use App\Import\ImportStats;
use App\Import\Listeners\StoresImportHistory;
use App\Import\ReasonForSkippingBookmark;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Import\TagsCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoresImportHistoryTest extends TestCase
{
    #[Test]
    public function workflow(): void
    {
        $this->expectNotToPerformAssertions();

        $bookmark = new Bookmark('Invalid Url', new TagsCollection([]), 22);
        $listener = new StoresImportHistory(new ImportData('foo', ImportSource::CHROME, 22, []));

        $listener->importStarted($bookmark);
        $listener->bookmarkImported($bookmark);

        $listener->importStarted($bookmark);
        $listener->bookmarkSkipped(ReasonForSkippingBookmark::INVALID_TAG);

        $listener->importStarted($bookmark);
        $listener->importFailed($bookmark, ImportBookmarksStatus::FAILED_DUE_TO_INVALID_BOOKMARK_URL);

        $listener->importStarted($bookmark);
        $listener->bookmarkNotProcessed($bookmark);

        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats()));
    }
}
