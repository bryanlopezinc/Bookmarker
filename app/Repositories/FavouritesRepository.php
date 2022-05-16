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
use App\Models\UserResourcesCount;
use App\PaginationData;
use App\QueryColumns\BookmarkQueryColumns;
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
        $attributes = [
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ];

        $favouritesCount = UserResourcesCount::query()->firstOrCreate($attributes, ['count' => $amount, ...$attributes]);

        if (!$favouritesCount->wasRecentlyCreated) {
            $favouritesCount->increment('count', $amount);
        }
    }

    public function exists(ResourceID $bookmarkId, UserID $userId): bool
    {
        return Favourite::where([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ])->exists();
    }

    public function duplicates(UserID $userID, ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        return Favourite::where('user_id', $userID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->get('bookmark_id')
            ->pipe(fn (Collection $favourites) => ResourceIDsCollection::fromNativeTypes($favourites->pluck('bookmark_id')->all()));
    }

    public function delete(ResourceID $bookmarkId, UserID $userId): bool
    {
        $deleted = Favourite::query()->where([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ])->delete();

        $this->decrementFavouritesCount($userId);

        return (bool) $deleted;
    }

    public function decrementFavouritesCount(UserID $userId, int $amount = 1): void
    {
        UserResourcesCount::query()->where([
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ])->decrement('count', $amount);
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserID $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favourites = Model::WithQueryOptions(BookmarkQueryColumns::new())
            ->join('favourites', 'favourites.bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favourites->setCollection(
            $favourites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }
}
