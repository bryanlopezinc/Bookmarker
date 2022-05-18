<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserResourcesCount;
use App\ValueObjects\UserID;

final class BookmarksCountRepository
{
    public function incrementUserBookmarksCount(UserID $userId): void
    {
        $attributes = [
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ];

        $bookmarksCount = UserResourcesCount::query()->firstOrCreate($attributes, ['count' => 1, ...$attributes]);

        if (!$bookmarksCount->wasRecentlyCreated) {
            $bookmarksCount->increment('count');
        }
    }

    public function decrementUserBookmarksCount(UserID $userId, int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        UserResourcesCount::query()->where([
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ])->decrement('count', $count);
    }
}
