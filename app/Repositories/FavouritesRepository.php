<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Favourite;
use App\ValueObjects\ResourceId;
use App\ValueObjects\UserId;

final class FavouritesRepository
{
    public function create(ResourceId $bookmarkId, UserId $userId): bool
    {
        Favourite::query()->create([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ]);

        return true;
    }

    public function exists(ResourceId $bookmarkId, UserId $userId): bool
    {
        return Favourite::where([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ])->exists();
    }

    public function delete(ResourceId $bookmarkId, UserId $userId): bool
    {
        return (bool) Favourite::query()->where([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ])->delete();
    }
}
