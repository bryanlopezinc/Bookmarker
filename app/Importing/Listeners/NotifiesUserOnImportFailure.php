<?php

declare(strict_types=1);

namespace App\Importing\Listeners;

use App\Importing\ImportBookmarksOutcome;
use App\Importing\Contracts;
use App\Models\User;
use App\Importing\Notifications\ImportFailedNotification;

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
