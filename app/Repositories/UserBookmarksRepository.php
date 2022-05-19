<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UserBookmarksFilters;
use App\QueryColumns\BookmarkQueryColumns as Columns;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class UserBookmarksRepository
{
    public function __construct(private FavouritesRepository $favouritesRepository)
    {
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function userBookmarks(UserBookmarksFilters $filters): Paginator
    {
        $builder = Model::WithQueryOptions(new Columns());

        if ($filters->hasCustomSite) {
            $builder->where('site_id', $filters->siteId->toInt());
        }

        if ($filters->hasTags) {
            $builder->whereHas('tags', function (Builder $builder) use ($filters) {
                $builder->whereIn('name', $filters->tags->toStringCollection()->uniqueStrict()->all());
            });
        }

        if ($filters->wantsUntaggedBookmarks) {
            $builder->whereDoesntHave('tags');
        };

        if ($filters->hasSortCriteria) {
            $builder->orderBy('bookmarks.id', $filters->sortCriteria->value);
        }

        /** @var Paginator */
        $result = $builder->where('user_id', $filters->userId->toInt())->simplePaginate($filters->pagination->perPage(), page: $filters->pagination->page());

        $collection = $this->setIsUserFavouriteAttributeOnBookmarks($result->getCollection(), $filters->userId);

        return $result->setCollection(
            $collection->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }

    private function setIsUserFavouriteAttributeOnBookmarks(Collection $bookmarks, UserID $userID): Collection
    {
        $bookmarkIDsFavouritedByUser = $this->favouritesRepository->getUserFavouritesFrom(
            ResourceIDsCollection::fromNativeTypes($bookmarks->pluck('id')),
            $userID
        )->asIntegers();

        return $bookmarks->map(function (Model $bookmark) use ($bookmarkIDsFavouritedByUser) {
            $bookmark->setAttribute('is_user_favourite', $bookmarkIDsFavouritedByUser->containsStrict($bookmark->id));

            return $bookmark;
        });
    }
}
