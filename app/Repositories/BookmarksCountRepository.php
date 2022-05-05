<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserResourcesCount;
use App\ValueObjects\UserId;

final class BookmarksCountRepository
{
    public function incrementUserBookmarksCount(UserId $userId): void
    {
        $attributes = [
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ];

        $bookmarksCount = UserResourcesCount::query()->firstOrCreate($attributes, ['count' => 1, ...$attributes]);

        if ( ! $bookmarksCount->wasRecentlyCreated) {
            $bookmarksCount->increment('count');
        }
    }

    public function decrementUserBookmarksCount(UserId $userId, int $count = 1): void
    {
        UserResourcesCount::query()->where([
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::BOOKMARKS_TYPE
        ])->decrement('count', $count);
    }
}
