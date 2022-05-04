<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Favourite;
use App\ValueObjects\ResourceId;
use App\ValueObjects\UserId;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\PaginationData;
use App\QueryColumns\BookmarkQueryColumns;
use Illuminate\Pagination\Paginator;

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

    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserId $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favourites = Model::WithQueryOptions(BookmarkQueryColumns::new())
            ->join('favourites', 'bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favourites->setCollection($favourites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build()));
    }
}
