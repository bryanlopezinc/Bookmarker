<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\FetchUserBookmarksRequestData;
use App\QueryColumns\BookmarkQueryColumns as Columns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class BookmarksRepository
{
    public function findById(ResourceID $bookmarkId, Columns $columns = new Columns()): Bookmark|false
    {
        $result = $this->findManyById($bookmarkId->toCollection(), $columns);

        return $result->isEmpty() ? false : $result->sole();
    }

    /**
     * @return Collection<Bookmark>
     */
    public function findManyById(ResourceIDsCollection $IDs, Columns $columns = new Columns()): Collection
    {
        return Model::WithQueryOptions($columns)
            ->whereIn('id', $IDs->asIntegers()->unique()->all())
            ->get()
            ->map(function (Model $bookmark) use ($columns): Bookmark {
                if (!$columns->has('id')) {
                    $bookmark->offsetUnset('id');
                }

                return BookmarkBuilder::fromModel($bookmark)->build();
            })->pipeInto(Collection::class);
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function userBookmarks(FetchUserBookmarksRequestData $userQuery): Paginator
    {
        $builder = Model::WithQueryOptions(new Columns());

        if ($userQuery->hasCustomSite) {
            $builder->where('site_id', $userQuery->siteId->toInt());
        }

        if ($userQuery->hasTags) {
            $builder->whereHas('tags', function (Builder $builder) use ($userQuery) {
                $builder->whereIn('name', $userQuery->tags->toStringCollection()->uniqueStrict()->all());
            });
        }

        if ($userQuery->wantsUntaggedBookmarks) {
            $builder->whereDoesntHave('tags');
        };

        if ($userQuery->hasSortCriteria) {
            $builder->orderBy('bookmarks.id', $userQuery->sortCriteria->value);
        }

        /** @var Paginator */
        $result = $builder->where('user_id', $userQuery->userId->toInt())
            ->simplePaginate($userQuery->pagination->perPage(), page: $userQuery->pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }
}
