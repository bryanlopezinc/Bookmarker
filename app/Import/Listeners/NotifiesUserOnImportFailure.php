<?php

declare(strict_types=1);

namespace App\Import\Listeners;

use App\Import\ImportBookmarksOutcome;
use App\Import\Contracts;
use App\Models\User;
use App\Notifications\ImportFailedNotification;

final class NotifiesUserOnImportFailure implements Contracts\ImportsEndedListenerInterface
{
    public function __construct(private readonly User $user, private readonly string $importId)
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

        $this->user->notify(new ImportFailedNotification($this->importId, $result));
    }
}
