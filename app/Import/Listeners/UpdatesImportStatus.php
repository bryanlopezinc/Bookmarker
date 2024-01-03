<?php

declare(strict_types=1);

namespace App\Import\Listeners;

use App\Import\ImportBookmarkRequestData;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Models\Import;
use App\Import\Contracts;

final class UpdatesImportStatus implements
    Contracts\ImportsStartedListenerInterface,
    Contracts\ImportsEndedListenerInterface
{
    private string $importId;

    /**
     * {@inheritdoc}
     */
    public function importsStarted(ImportBookmarkRequestData $data): void
    {
        $this->importId = $data->importId();

        Import::query()
            ->where('import_id', $data->importId())
            ->update(['status' => ImportBookmarksStatus::IMPORTING]);
    }

    public function setImportId(string $importId): void
    {
        $this->importId =  $importId;
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        Import::query()
            ->where('import_id', $this->importId)
            ->sole()
            ->update([
                'status'             => $result->status->isSuccessful() ? ImportBookmarksStatus::IMPORTING : $result->status->value,
                'statistics'         => $result->statistics
            ]);
    }
}
