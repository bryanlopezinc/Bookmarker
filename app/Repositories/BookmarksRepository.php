<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\QueryColumns\BookmarkQueryColumns as Columns;
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
}
