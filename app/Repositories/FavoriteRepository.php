<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Favorite;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\UserFavoritesCount;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes;
use Illuminate\Pagination\Paginator;

final class FavoriteRepository
{
    public function create(ResourceID $bookmarkId, UserID $userId): bool
    {
        return $this->createMany($bookmarkId->toCollection(), $userId);
    }

    public function createMany(ResourceIDsCollection $bookmarkIds, UserID $userId): bool
    {
        Favorite::insert($bookmarkIds->asIntegers()->map(fn (int $bookmarkID) => [
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkID
        ])->all());

        $this->incrementFavoritesCount($userId, $bookmarkIds->count());

        return true;
    }

    private function incrementFavoritesCount(UserID $userId, int $amount = 1): void
    {
        $favoritesCount = UserFavoritesCount::query()->firstOrCreate(['user_id' => $userId->toInt()], ['count' => $amount]);

        if (!$favoritesCount->wasRecentlyCreated) {
            $favoritesCount->increment('count', $amount);
        }
    }

    /**
     * Check  if ALL of the given bookmarks exists in user favorites
     */
    public function containsAll(ResourceIDsCollection $bookmarkIDs, UserID $userId): bool
    {
        return $this->intersect($bookmarkIDs, $userId)->count() === $bookmarkIDs->count();
    }

    /**
     * Check  if ANY of the given bookmarks exists in user favorites
     */
    public function contains(ResourceIDsCollection $bookmarkIDs, UserID $userID): bool
    {
        return $this->intersect($bookmarkIDs, $userID)->isNotEmpty();
    }

    /**
     * Get only the bookmark IDs which exists in user favorites record from the given bookmarkIDs.
     */
    public function intersect(ResourceIDsCollection $bookmarkIDs, UserID $userID): ResourceIDsCollection
    {
        return ResourceIDsCollection::fromNativeTypes(
            Favorite::where('user_id', $userID->toInt())
                ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
                ->get(['bookmark_id'])
                ->pluck('bookmark_id')
        );
    }

    public function delete(ResourceIDsCollection $bookmarkIDs, UserID $userId): bool
    {
        $deleted = Favorite::where('user_id', $userId->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())
            ->delete();

        $this->decrementFavoritesCount($userId, $deleted);

        return (bool) $deleted;
    }

    private function decrementFavoritesCount(UserID $userId, int $amount = 1): void
    {
        if ($amount < 1) {
            return;
        }

        UserFavoritesCount::query()->where('user_id', $userId->toInt())->decrement('count', $amount);
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserID $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favorites = Model::WithQueryOptions(BookmarkAttributes::new())
            ->join('favourites', 'favourites.bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favorites->setCollection(
            $favorites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->isUserFavorite(true)->build())
        );
    }
}
