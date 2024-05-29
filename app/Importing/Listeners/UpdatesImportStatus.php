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
    private int $importId;

    /**
     * {@inheritdoc}
     */
    public function importsStarted(ImportBookmarkRequestData $data): void
    {
        $this->importId = $data->importId();

        Import::query()->whereKey($data->importId())->update(['status' => ImportBookmarksStatus::IMPORTING]);
    }

    public function setImportId(int $importId): void
    {
        $this->importId =  $importId;
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        Import::query()
            ->whereKey($this->importId)
            ->sole()
            ->update([
                'status'     => $result->status->isSuccessful() ? ImportBookmarksStatus::IMPORTING : $result->status->value,
                'statistics' => $result->statistics
            ]);
    }
}
