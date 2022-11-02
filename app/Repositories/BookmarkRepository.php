<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Exceptions\BookmarkNotFoundException;
use App\QueryColumns\BookmarkAttributes;
use Illuminate\Support\Collection;

class BookmarkRepository
{
    /**
     * @throws BookmarkNotFoundException
     */
    public function findById(ResourceID $bookmarkId, BookmarkAttributes $onlyAttributes = new BookmarkAttributes()): Bookmark
    {
        $result = $this->findManyById($bookmarkId->toCollection(), $onlyAttributes);

        if ($result->isEmpty()) {
            throw new BookmarkNotFoundException;
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
}