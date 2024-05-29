<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\DataTransferObjects\ImportedBookmark;
use App\Importing\Enums\ImportSource;
use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Listeners\TransferImportsToBookmarksStore;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Models\Import;
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
        $import = ImportFactory::new()->create();

        ImportHistoryFactory::times(3)->create(['import_id' => $importId = $import->id]);

        $serviceMock = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $serviceMock->expects($this->never())->method('fromImport');

        $listener = new TransferImportsToBookmarksStore(
            new ImportBookmarkRequestData($importId, ImportSource::CHROME, 33, [], '94e35144-3ab7-47c1-8109-aa1c81e590dd'),
            $serviceMock
        );

        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats(10, 2, 50, 2, 1)));
    }

    #[Test]
    public function willTransferImports(): void
    {
        $import = ImportFactory::new()->create();
        $successfulImportHistory = ImportHistoryFactory::new()->create(['import_id' => $import->id]);
        ImportHistoryFactory::new()->skipped()->create(['import_id' => $import->id]);
        ImportHistoryFactory::new()->failed()->create(['import_id' => $import->id]);

        $serviceMock = $this->getMockBuilder(CreateBookmarkService::class)->getMock();

        $serviceMock->expects($this->once())
            ->method('fromImport')
            ->willReturnCallback(function (ImportedBookmark $bookmark) use ($successfulImportHistory) {
                $this->assertEquals($bookmark->url->toString(), $successfulImportHistory->url);
            });

        $listener = new TransferImportsToBookmarksStore(
            new ImportBookmarkRequestData($import->id, ImportSource::CHROME, 33, [], '94e35144-3ab7-47c1-8109-aa1c81e590dd'),
            $serviceMock
        );

        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats(10, 2, 50, 2, 1)));

        $this->assertEquals(
            Import::query()->where('id', $import->id)->sole()->status,
            ImportBookmarksStatus::SUCCESS
        );
    }
}
