<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UserBookmarksFilters;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes as Columns;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class UserBookmarksRepository
{
    public function __construct(private FavouriteRepository $userFavourites)
    {
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function fetch(UserID $userID, UserBookmarksFilters $filters): Paginator
    {
        $query = Model::WithQueryOptions(new Columns())->where('user_id', $userID->toInt());

        if (!$filters->hasAnyFilter()) {
            return $this->paginate($query->latest('bookmarks.id'), $userID, $filters->pagination);
        }

        if ($filters->wantsOnlyBookmarksFromParticularSite) {
            $query->where('site_id', $filters->siteId->toInt());
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

        if ($filters->wantsBooksmarksWithDeadLinks) {
            $query->where('bookmarks_health.is_healthy', false);
        }

        return $this->paginate($query, $userID, $filters->pagination);
    }

    /**
     * @param Builder $query
     */
    private function paginate($query, UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        $collection = $this->setIsUserFavouriteAttributeOnBookmarks($result->getCollection(), $userID);

        return $result->setCollection(
            $collection->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }

    private function setIsUserFavouriteAttributeOnBookmarks(Collection $bookmarks, UserID $userID): Collection
    {
        $bookmarkIDsFavouritedByUser = $this->userFavourites->intersect(
            ResourceIDsCollection::fromNativeTypes($bookmarks->pluck('id')),
            $userID
        )->asIntegers();

        return $bookmarks->map(function (Model $bookmark) use ($bookmarkIDsFavouritedByUser) {
            $bookmark->setAttribute('is_user_favourite', $bookmarkIDsFavouritedByUser->containsStrict($bookmark->id));

            return $bookmark;
        });
    }
}
