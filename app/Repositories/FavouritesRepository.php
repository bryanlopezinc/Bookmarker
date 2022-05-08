<?php

declare(strict_types=1);

namespace App\Repositories;

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

final class FavouritesRepository
{
    public function create(ResourceID $bookmarkId, UserID $userId): bool
    {
        Favourite::query()->create([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ]);

        $this->incrementFavouritesCount($userId);

        return true;
    }

    private function incrementFavouritesCount(UserID $userId): void
    {
        $attributes = [
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ];

        $favouritesCount = UserResourcesCount::query()->firstOrCreate($attributes, ['count' => 1, ...$attributes]);

        if (!$favouritesCount->wasRecentlyCreated) {
            $favouritesCount->increment('count');
        }
    }

    public function exists(ResourceID $bookmarkId, UserID $userId): bool
    {
        return Favourite::where([
            'user_id' => $userId->toInt(),
            'bookmark_id' => $bookmarkId->toInt()
        ])->exists();
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

    private function decrementFavouritesCount(UserID $userId): void
    {
        UserResourcesCount::query()->where([
            'user_id' => $userId->toInt(),
            'type' => UserResourcesCount::FAVOURITES_TYPE
        ])->decrement('count');
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserID $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favourites = Model::WithQueryOptions(BookmarkQueryColumns::new())
            ->join('favourites', 'bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favourites->setCollection(
            $favourites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }
}
