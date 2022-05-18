<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\UserBookmarksFilters;
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
            ->whereIn('bookmarks.id', $IDs->asIntegers()->unique()->all())
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

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }
}
