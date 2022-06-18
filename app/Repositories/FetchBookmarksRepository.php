<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Exceptions\BookmarkNotFoundException;
use App\QueryColumns\BookmarkQueryColumns as Columns;
use Illuminate\Support\Collection;

final class FetchBookmarksRepository
{
    /**
     * @throws BookmarkNotFoundException
     */
    public function findById(ResourceID $bookmarkId, Columns $columns = new Columns()): Bookmark
    {
        $result = $this->findManyById($bookmarkId->toCollection(), $columns);

        if ($result->isEmpty()) {
            throw new BookmarkNotFoundException;
        }

        return $result->sole();
    }

    /**
     * @return Collection<Bookmark>
     */
    public function findManyById(ResourceIDsCollection $IDs, Columns $columns = new Columns()): Collection
    {
        $originalRequestedColums = $columns->toArray();

        if (!$columns->isEmpty() && !$columns->has($attributesNeededToCheckBookmarkHealth = ['id', 'url'])) {
            $columns = new Columns(collect($columns)->push(...$attributesNeededToCheckBookmarkHealth)->unique()->all());
        }

        return Model::WithQueryOptions($columns)
            ->whereIn('bookmarks.id', $IDs->asIntegers()->unique()->all())
            ->get()
            ->map(function (Model $bookmark) use ($originalRequestedColums): Bookmark {
                if (!empty($originalRequestedColums)) {
                    collect($bookmark->toArray())
                        ->keys()
                        ->diff($originalRequestedColums)
                        ->each(fn (string $attrbute) => $bookmark->offsetUnset($attrbute));
                }

                return BookmarkBuilder::fromModel($bookmark)->build();
            })->pipeInto(Collection::class);
    }
}
