<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\Enums\ImportSource;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Listeners\UpdatesImportStatus;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Models\Import;
use Database\Factories\ImportFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatesImportStatusTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function willUpdateStatusWhenImportStarts(): void
    {
        $pendingImport = ImportFactory::new()->create();

        $listener = new UpdatesImportStatus();
        $listener->importsStarted(new ImportBookmarkRequestData($pendingImport->import_id, ImportSource::CHROME, 33, []));

        $runningImport = Import::query()->where('import_id', $pendingImport->import_id)->sole();
        $this->assertEquals($runningImport->status, ImportBookmarksStatus::IMPORTING);
    }

    #[Test]
    public function whenImportWasSuccessful(): void
    {
        $runningImport = ImportFactory::new()->importing()->create();

        $listener = new UpdatesImportStatus();
        $listener->setImportId($runningImport->import_id);
        $listener->importsEnded(ImportBookmarksOutcome::success($stats = new ImportStats(10, 2, 50, 2, 1)));

        /** @var Import */
        $successfulImport = Import::query()->where('import_id', $runningImport->import_id)->sole();
        $this->assertEquals($successfulImport->status, ImportBookmarksStatus::IMPORTING);
        $this->assertEquals($successfulImport->statistics, $stats);
    }

    #[Test]
    public function whenImportWasNotSuccessful(): void
    {
        $runningImport = ImportFactory::new()->importing()->create();

        $listener = new UpdatesImportStatus();
        $listener->setImportId($runningImport->import_id);
        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, $stats = new ImportStats(10, 2, 50, 2, 1)));

        /** @var Import */
        $failedImport = Import::query()->where('import_id', $runningImport->import_id)->sole();
        $this->assertEquals($failedImport->status, ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR);
        $this->assertEquals($failedImport->statistics, $stats);
    }
}
