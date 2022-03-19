<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BookmarksCount;
use App\ValueObjects\UserId;

final class BookmarksCountRepository
{
    public function incrementUserBookmarksCount(UserId $userId): void
    {
        $bookmarksCount = BookmarksCount::query()->firstOrCreate(['user_id' => $userId->toInt()], [
            'user_id' => $userId->toInt(),
            'count'   => 1
        ]);

        if (!$bookmarksCount->wasRecentlyCreated) {
            $bookmarksCount->increment('count');
        }
    }

    public function decrementUserBookmarksCount(UserId $userId, int $count = 1): void
    {
        BookmarksCount::query()->where('user_id', $userId->toInt())->decrement('count', $count);
    }
}
