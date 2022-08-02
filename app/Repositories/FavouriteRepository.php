<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Favourite;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\UserFavouritesCount;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes;
use Illuminate\Pagination\Paginator;

final class FavouriteRepository
{
    public function create(ResourceID $bookmarkId, UserID $userId): bool
    {
        return $this->createMany($bookmarkId->toCollection(), $userId);
    }

    public function createMany(ResourceIDsCollection $bookmarkIds, UserID $userId): bool
    {
        Favourite::insert($bookmarkIds->asIntegers()->map(fn (int $bookmarkID) => [
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkID
        ])->all());

        $this->incrementFavouritesCount($userId, $bookmarkIds->count());

        return true;
    }

    private function incrementFavouritesCount(UserID $userId, int $amount = 1): void
    {
        $favouritesCount = UserFavouritesCount::query()->firstOrCreate(['user_id' => $userId->toInt()], ['count' => $amount]);

        if (!$favouritesCount->wasRecentlyCreated) {
            $favouritesCount->increment('count', $amount);
        }
    }

    /**
     * Check  if ALL of the given bookmarks exists in user favourites
     */
    public function containsAll(ResourceIDsCollection $bookmarkIDs, UserID $userId): bool
    {
        return $this->intersect($bookmarkIDs, $userId)->count() === $bookmarkIDs->count();
    }

    /**
     * Check  if ANY of the given bookmarks exists in user favourites
     */
    public function contains(ResourceIDsCollection $bookmarkIDs, UserID $userID): bool
    {
        return $this->intersect($bookmarkIDs, $userID)->isNotEmpty();
    }

    /**
     * Get only the bookmark IDs which exists in user favourites record from the given bookmarkIDs.
     */
    public function intersect(ResourceIDsCollection $bookmarkIDs, UserID $userID): ResourceIDsCollection
    {
        return ResourceIDsCollection::fromNativeTypes(
            Favourite::where('user_id', $userID->toInt())
                ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
                ->get(['bookmark_id'])
                ->pluck('bookmark_id')
        );
    }

    public function delete(ResourceIDsCollection $bookmarkIDs, UserID $userId): bool
    {
        $deleted = Favourite::where('user_id', $userId->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())
            ->delete();

        $this->decrementFavouritesCount($userId, $deleted);

        return (bool) $deleted;
    }

    private function decrementFavouritesCount(UserID $userId, int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        UserFavouritesCount::query()->where('user_id', $userId->toInt())->decrement('count', $amount);
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserID $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favourites = Model::WithQueryOptions(BookmarkAttributes::new())
            ->join('favourites', 'favourites.bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favourites->setCollection(
            $favourites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->isUserFavourite(true)->build())
        );
    }
}
