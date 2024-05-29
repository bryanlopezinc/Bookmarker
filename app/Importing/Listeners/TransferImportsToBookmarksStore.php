<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\DataTransferObjects\ImportedBookmark;
use App\Importing\Enums\BookmarkImportStatus;
use App\Importing\Contracts;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Models\Import;
use App\Importing\Models\ImportHistory;
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

        Import::query()->whereKey($this->data->importId())->update(['status' => ImportBookmarksStatus::SUCCESS]);
    }
}
