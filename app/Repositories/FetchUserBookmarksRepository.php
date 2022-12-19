<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UserBookmarksFilters;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes as Columns;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

final class FetchUserBookmarksRepository
{
    /**
     * @return Paginator<Bookmark>
     */
    public function fetch(UserID $userID, UserBookmarksFilters $filters): Paginator
    {
        $query = Model::WithQueryOptions(new Columns())
            ->where('bookmarks.user_id', $userID->value())
            ->addSelect('favourites.bookmark_id as isFavourite')
            ->leftJoin('favourites', 'favourites.bookmark_id', '=', 'bookmarks.id');

        if (!$filters->hasAnyFilter()) {
            return $this->paginate($query->latest('bookmarks.id'), $filters->pagination);
        }

        if ($filters->wantsOnlyBookmarksFromParticularSource) {
            $query->where('source_id', $filters->sourceID->value());
        }

        if ($filters->wantsBookmarksWithSpecificTags) {
            $query->whereHas('tags', function (Builder $builder) use ($filters) {
                $builder->whereIn('name', $filters->tags->toStringCollection()->uniqueStrict()->all());
            });
        }

        if ($filters->wantsUntaggedBookmarks) {
            $query->whereDoesntHave('tags');
        };

        if ($filters->hasSortCriteria) {
            $query->orderBy('bookmarks.id', $filters->sortCriteria->value);
        }

        if ($filters->wantsBookmarksWithDeadLinks) {
            $query->where('bookmarks_health.is_healthy', false);
        }

        return $this->paginate($query, $filters->pagination);
    }

    /**
     * @param Builder $query
     */
    private function paginate($query, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()
                ->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)
                    ->isUserFavorite((bool) $bookmark->isFavourite)
                    ->build())
        );
    }
}
