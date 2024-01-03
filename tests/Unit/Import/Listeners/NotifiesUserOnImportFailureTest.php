<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Listeners;

use App\Import\ImportStats;
use App\Import\Listeners\NotifiesUserOnImportFailure;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Models\User;
use App\Notifications\ImportFailedNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotifiesUserOnImportFailureTest extends TestCase
{
    #[Test]
    public function willNotNotifyUserWhenImportWasSuccessful(): void
    {
        Notification::fake();

        $listener = new NotifiesUserOnImportFailure(new User(), '');
        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats()));

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotifyUserOnFailure(): void
    {
        Notification::fake();

        $listener = new NotifiesUserOnImportFailure(new User(), '');
        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats()));

        Notification::assertSentTimes(ImportFailedNotification::class, 1);
    }
}
