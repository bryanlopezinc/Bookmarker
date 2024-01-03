<?php

declare(strict_types=1);

namespace App\Import\Listeners;

use App\Import\ImportBookmarkRequestData;
use App\DataTransferObjects\Import\ImportedBookmark;
use App\Import\BookmarkImportStatus;
use App\Import\Contracts;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Models\Import;
use App\Models\ImportHistory;
use App\Services\CreateBookmarkService;
use App\ValueObjects\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TransferImportsToBookmarksStore implements Contracts\ImportsEndedListenerInterface
{
    private ImportBookmarkRequestData $data;
    private CreateBookmarkService $service;

    public function __construct(ImportBookmarkRequestData $data, CreateBookmarkService $service = null)
    {
        $this->data = $data;
        $this->service = $service ?: new CreateBookmarkService();
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        $importedAt = now();

        if ($result->status->failed() || $result->statistics->totalImported === 0) {
            return;
        }

        DB::transaction(function () use ($importedAt) {
            ImportHistory::query()
                ->where('import_id', $this->data->importId())
                ->where('status', BookmarkImportStatus::SUCCESS->value)
                ->chunkById(200, function (Collection $chunk) use ($importedAt) {
                    $chunk->each(function (ImportHistory $importHistory) use ($importedAt) {
                        $this->service->fromImport(new ImportedBookmark(
                            new Url($importHistory->url),
                            $importedAt,
                            $this->data->userId(),
                            $importHistory->tags->resolved(),
                            $this->data->source()
                        ));
                    });
                });
        });

        Import::query()
            ->where('import_id', $this->data->importId())
            ->update(['status' => ImportBookmarksStatus::SUCCESS]);
    }
}
