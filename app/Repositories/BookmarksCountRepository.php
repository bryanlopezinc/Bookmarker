<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserBookmarksCount;
use App\ValueObjects\UserID;

final class BookmarksCountRepository
{
    public function incrementUserBookmarksCount(UserID $userId): void
    {
        $bookmarksCount = UserBookmarksCount::query()->firstOrCreate(['user_id' => $userId->toInt()], ['count' => 1,]);

        if (!$bookmarksCount->wasRecentlyCreated) {
            $bookmarksCount->increment('count');
        }
    }
}
