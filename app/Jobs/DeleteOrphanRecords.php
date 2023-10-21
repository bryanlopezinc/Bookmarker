<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeleteOrphanRecords
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        Bookmark::query()
            ->whereNotExists(function (&$query) {
                $query = User::query()
                    ->getQuery()
                    ->select('id')
                    ->whereRaw('id = bookmarks.user_id');
            })
            ->chunkById(200, function (Collection $chunk) {
                $chunk->toQuery()->delete();
            });

        Folder::query()
            ->whereNotExists(function (&$query) {
                $query = User::query()
                    ->getQuery()
                    ->select('id')
                    ->whereRaw('id = folders.user_id');
            })
            ->chunkById(200, function (Collection $chunk) {
                $chunk->toQuery()->delete();
            });
    }
}
