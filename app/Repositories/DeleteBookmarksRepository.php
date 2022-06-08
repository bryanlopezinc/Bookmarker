<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarksRepository
{
    public function deleteManyFor(UserID $userId, ResourceIDsCollection $bookmarkIds): bool
    {
        return (bool) Model::query()->where('user_id', $userId->toInt())->whereIn('id', $bookmarkIds->asIntegers())->delete();
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSite(ResourceID $siteId, UserID $userId): bool
    {
        return Model::query()->where([
            'site_id' => $siteId->toInt(),
            'user_id' => $userId->toInt()
        ])->chunkById(100, function (Collection $chunk) {
            $chunk->toQuery()->delete();
        });
    }
}
