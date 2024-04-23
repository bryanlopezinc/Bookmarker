<?php

declare(strict_types=1);

namespace App\Importing\tests\Unit\Listeners;

use App\Importing\DataTransferObjects\ImportStats;
use App\Importing\Listeners\NotifiesUserOnImportFailure;
use App\Importing\ImportBookmarksOutcome;
use App\Importing\Enums\ImportBookmarksStatus;
use App\Models\User;
use Database\Factories\ImportFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotifiesUserOnImportFailureTest extends TestCase
{
    #[Test]
    public function willNotNotifyUserWhenImportWasSuccessful(): void
    {
        $user = $this->getMockBuilder(User::class)->getMock();

        $user->expects($this->never())->method('notify');

        $listener = new NotifiesUserOnImportFailure($user, 22);
        $listener->importsEnded(ImportBookmarksOutcome::success(new ImportStats()));
    }

    #[Test]
    public function willNotifyUserOnFailure(): void
    {
        $user = $this->getMockBuilder(User::class)->getMock();
        $import = ImportFactory::new()->create();

        $user->expects($this->once())->method('notify');

        $listener = new NotifiesUserOnImportFailure($user, $import->id);
        $listener->importsEnded(ImportBookmarksOutcome::failed(ImportBookmarksStatus::FAILED_DUE_TO_SYSTEM_ERROR, new ImportStats()));
    }
}
