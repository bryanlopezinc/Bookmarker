<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Listeners;

use App\Import\ImportStats;
use App\Import\Listeners\NotifiesUserOnImportFailure;
use App\Import\ImportBookmarksOutcome;
use App\Import\ImportBookmarksStatus;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NotifiesUserOnImportFailureTest extends TestCase
{
    #[Test]
    public function willNotNotifyUserWhenImportWasSuccessful(): void
    {
        $user = $this->getMockBuilder(User::class)->getMock();

        $user->expects($this->never())->method('notify');

        $listener = new NotifiesUserOnImportFailure($user, '');
        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats()));
    }

    #[Test]
    public function willNotifyUserOnFailure(): void
    {
        $user = $this->getMockBuilder(User::class)->getMock();

        $user->expects($this->once())->method('notify');

        $listener = new NotifiesUserOnImportFailure($user, '');
        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats()));
    }
}
