<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\Observers\BookmarkObserver;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarkRepository
{
    public function delete(ResourceIDsCollection $bookmarkIDs): bool
    {
        //Prevent bookmark from being health checked if it has been retrieved
        $bookmarkIDs->asIntegers()->each(function (int $bookmarkID) {
            (new BookmarkObserver)->deleting(new Model(['id' => $bookmarkID]));
        });

        return (bool) Model::whereIn('id', $bookmarkIDs->asIntegers())->delete();
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSource(ResourceID $sourceID, UserID $userId): bool
    {
        //Prevent bookmark from being health checked when retrieved.
        return Model::withoutEvents(function () use ($sourceID, $userId) {
            return Model::query()->where([
                'site_id' => $sourceID->toInt(),
                'user_id' => $userId->toInt()
            ])->chunkById(100, function (Collection $chunk) {
                $chunk->toQuery()->delete();
            });
        });
    }
}
