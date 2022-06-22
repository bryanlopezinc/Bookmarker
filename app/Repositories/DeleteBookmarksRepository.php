<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\Observers\BookmarkObserver;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarksRepository
{
    public function deleteManyFor(UserID $userId, ResourceIDsCollection $bookmarkIds): bool
    {
        //Prevent bookmark from being health checked if it has been retrieved
        $bookmarkIds->asIntegers()->each(function (int $bookmarkID) {
            (new BookmarkObserver)->deleting(new Model(['id' => $bookmarkID]));
        });

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
