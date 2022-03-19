<?php

declare(strict_types=1);

namespace App\Repositories;

use App\BookmarkColumns;
use App\ValueObjects\UserId;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

final class FetchUserFavouritesRepository
{
    /**
     * @return Paginator<Bookmark>
     */
    public function get(UserId $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $favourites = Model::WithQueryOptions(BookmarkColumns::new())
            ->join('favourites', 'bookmark_id', '=', 'bookmarks.id')
            ->where('favourites.user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $favourites->setCollection($favourites->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build()));
    }
}
