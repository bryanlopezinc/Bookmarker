<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Bookmark;
use App\Models\DeletedUser;
use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CleanDeletedUsersTable
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        $deleteUser = DeletedUser::first();

        if ($deleteUser === null) {
            return;
        }

        if ($this->allUserHasRecordsHasBeenDeleted($deleteUser)) {
            $deleteUser->delete();
        }
    }

    private function allUserHasRecordsHasBeenDeleted(DeletedUser $deletedUser): bool
    {
        return empty(array_filter([
            Bookmark::query()->where('user_id', $deletedUser->user_id)->exists(),
            Folder::query()->where('user_id', $deletedUser->user_id)->exists(),
        ]));
    }
}
