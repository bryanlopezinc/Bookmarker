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
use Illuminate\Support\Collection;

final class FavouritesRepository
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

    public function exists(ResourceIDsCollection $bookmarkIDs, UserID $userId): bool
    {
        $total = Favourite::where('user_id', $userId->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())
            ->get()
            ->count();

        return $bookmarkIDs->count() === $total;
    }

    public function duplicates(UserID $userID, ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        return Favourite::where('user_id', $userID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->get('bookmark_id')
            ->pipe(fn (Collection $favourites) => ResourceIDsCollection::fromNativeTypes($favourites->pluck('bookmark_id')->all()));
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

    /**
     * Get only the bookmark IDs which exists in user favourites record from the given bookmarkIDs.
     */
    public function getUserFavouritesFrom(ResourceIDsCollection $bookmarkIDs, UserID $userID): ResourceIDsCollection
    {
        return ResourceIDsCollection::fromNativeTypes(
            Favourite::query()
                ->where('user_id', $userID->toInt())
                ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
                ->get(['bookmark_id'])
                ->pluck('bookmark_id')
        );
    }
}
