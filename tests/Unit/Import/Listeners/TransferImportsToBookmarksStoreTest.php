<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Listeners;

use App\Import\ImportBookmarkRequestData;
use App\DataTransferObjects\Import\ImportedBookmark;
use App\Enums\ImportSource;
use App\Import\ImportStats;
use App\Import\Listeners\TransferImportsToBookmarksStore;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Models\Import;
use App\Services\CreateBookmarkService;
use Database\Factories\ImportFactory;
use Database\Factories\ImportHistoryFactory;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferImportsToBookmarksStoreTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function willNotTransferImportsWhenImportFailed(): void
    {
        ImportHistoryFactory::times(3)->create(['import_id' => $importId = $this->faker->uuid]);
        $serviceMock = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $serviceMock->expects($this->never())->method('fromImport');

        $listener = new TransferImportsToBookmarksStore(new ImportBookmarkRequestData($importId, ImportSource::CHROME, 33, []), $serviceMock);
        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats(10, 2, 50, 2, 1)));
    }

    #[Test]
    public function willTransferImports(): void
    {
        $import = ImportFactory::new()->create();
        $successfulImportHistory = ImportHistoryFactory::new()->create(['import_id' => $import->import_id]);
        ImportHistoryFactory::new()->skipped()->create(['import_id' => $import->import_id]);
        ImportHistoryFactory::new()->failed()->create(['import_id' => $import->import_id]);

        $serviceMock = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $serviceMock->expects($this->once())
            ->method('fromImport')
            ->willReturnCallback(function (ImportedBookmark $bookmark) use ($successfulImportHistory) {
                $this->assertEquals($bookmark->url->toString(), $successfulImportHistory->url);
            });

        $listener = new TransferImportsToBookmarksStore(new ImportBookmarkRequestData($import->import_id, ImportSource::CHROME, 33, []), $serviceMock);
        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats(10, 2, 50, 2, 1)));

        $this->assertEquals(
            Import::query()->where('import_id', $import->import_id)->sole()->status,
            ImportBookmarksStatus::SUCCESS
        );
    }
}
