<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\ImportBookmarksOutcome;
use App\Importing\Contracts;
use App\Importing\Models\Import;
use App\Models\User;
use App\Importing\Notifications\ImportFailedNotification;

final class NotifiesUserOnImportFailure implements Contracts\ImportsEndedListenerInterface
{
    public function __construct(private readonly User $user, private readonly int $importId)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function importsEnded(ImportBookmarksOutcome $result): void
    {
        if ($result->status->isSuccessful()) {
            return;
        }

        $this->user->notify(new ImportFailedNotification(
            Import::query()->whereKey($this->importId)->sole(['id', 'public_id']),
            $result
        ));
    }
}
