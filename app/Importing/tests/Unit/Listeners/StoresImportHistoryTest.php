<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData as ImportData;
use App\Importing\Enums\ImportSource;
use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Listeners\StoresImportHistory;
use App\Importing\Enums\ReasonForSkippingBookmark;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Collections\TagsCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoresImportHistoryTest extends TestCase
{
    #[Test]
    public function workflow(): void
    {
        $this->expectNotToPerformAssertions();

        $bookmark = new Bookmark('Invalid Url', new TagsCollection([]), 22);
        $listener = new StoresImportHistory(new ImportData(33, ImportSource::CHROME, 22, [], '94e35144-3ab7-47c1-8109-aa1c81e590dd'));

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
