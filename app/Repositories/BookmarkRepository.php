<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Exceptions\BookmarkNotFoundException;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class BookmarkRepository
{
    /**
     * @throws BookmarkNotFoundException
     */
    public function findById(
        ResourceID $bookmarkId,
        BookmarkAttributes $onlyAttributes = new BookmarkAttributes()
    ): Bookmark {
        $result = $this->findManyById($bookmarkId->toCollection(), $onlyAttributes);

        if ($result->isEmpty()) {
            throw new BookmarkNotFoundException();
        }

        return $result->sole();
    }

    /**
     * @return Collection<Bookmark>
     */
    public function findManyById(ResourceIDsCollection $IDs, ?BookmarkAttributes $columns = null): Collection
    {
        $columns = $columns ?: new BookmarkAttributes();

        return Model::WithQueryOptions($columns)
            ->whereIn('bookmarks.id', $IDs->asIntegers()->unique()->all())
            ->get()
            ->map(function (Model $bookmark) use ($columns): Bookmark {
                if (!$columns->isEmpty() && !$columns->has('id')) {
                    $bookmark->offsetUnset('id');
                }

                return BookmarkBuilder::fromModel($bookmark)->build();
            })
            ->pipeInto(Collection::class);
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function fetchPossibleDuplicates(Bookmark $bookmark, UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = Model::WithQueryOptions(new BookmarkAttributes())
            ->addSelect('favourites.bookmark_id as isFavourite')
            ->join('favourites', 'favourites.bookmark_id', '=', 'bookmarks.id', 'left outer')
            ->where('url_canonical_hash', $bookmark->canonicalUrlHash->value)
            ->where('bookmarks.user_id', $userID->value())
            ->whereNotIn('bookmarks.id', [$bookmark->id->value()])
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(function (Model $model) {
                return BookmarkBuilder::fromModel($model)
                    ->isUserFavorite((bool) $model->isFavourite)
                    ->build();
            })
        );
    }
}
