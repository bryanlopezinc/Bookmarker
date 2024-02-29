<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Importing\Models\Import;
use App\Importing\Contracts;

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
