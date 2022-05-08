<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\Models\Favourite;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteBookmarksRepository
{
    public function __construct(
        private BookmarksCountRepository $bookmarksCountRepository,
        private FavouritesRepository $favouritesRepository
    ) {
    }

    public function deleteMany(ResourceIDsCollection $bookmarkIds, UserID $userId): bool
    {
        // Get the count of bookmarks in user favourites table
        //which will be cascade deleted from user favourites table.
        $totalFavouritedToBeDeleted = $this->getFavouritedBookmarksCount($bookmarkIds, $userId);

        $totalBookmarksDeleted = Model::query()->whereIn('id', $bookmarkIds->asIntegers())->delete();

        if ($totalBookmarksDeleted > 0) {
            $this->bookmarksCountRepository->decrementUserBookmarksCount($userId, $totalBookmarksDeleted);
        }

        if ($totalFavouritedToBeDeleted > 0) {
            $this->favouritesRepository->decrementFavouritesCount($userId, $totalFavouritedToBeDeleted);
        }

        return (bool) $totalBookmarksDeleted;
    }

    /**
     * Get  the total amount of bookmarks that was added to favourites by user
     * from the bookmarkIDs to be deleted.
     */
    private function getFavouritedBookmarksCount(ResourceIDsCollection $bookmarkIds, UserID $userId): int
    {
        return Favourite::query()
            ->where('user_id', $userId->toInt())
            ->whereIn('bookmark_id', $bookmarkIds->asIntegers())
            ->count('id');
    }

    /**
     * Delete all bookmarks from a particular site
     */
    public function fromSite(ResourceID $siteId, UserID $userId): bool
    {
        return Model::query()->where([
            'site_id' => $siteId->toInt(),
            'user_id' => $userId->toInt()
        ])->chunkById(100, function (Collection $chunk) use ($userId) {

            // Get the count of bookmarks in user favourites table
            //which will be cascade deleted from user favourites table.
            $totalFavouritedToBeDeleted = $this->getFavouritedBookmarksCount(
                ResourceIDsCollection::fromNativeTypes($chunk->pluck('id')->all()),
                $userId
            );

            $totalBookmarksDeleted = $chunk->toQuery()->delete();

            if ($totalFavouritedToBeDeleted > 0) {
                $this->favouritesRepository->decrementFavouritesCount($userId, $totalFavouritedToBeDeleted);
            }

            if ($totalBookmarksDeleted > 0) {
                $this->bookmarksCountRepository->decrementUserBookmarksCount($userId, $chunk->count());
            }
        });
    }
}
