<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\DeletedUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneDeletedUsersData
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $deletedUser = DeletedUser::query()->first();

        if ($deletedUser === null) {
            return;
        }

        Bookmark::query()->where('user_id', $deletedUser->user_id)->chunkById(200, function (Collection $chunk) {
            $chunk->toQuery()->delete();
        });

        Folder::query()->where('user_id', $deletedUser->user_id)->chunkById(200, function (Collection $chunk) {
            $chunk->toQuery()->delete();
        });
    }
}