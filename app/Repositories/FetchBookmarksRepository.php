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

class FetchBookmarksRepository
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
        $originalRequestedColumns = $columns->toArray();

        //Force bookmark to be health checked by model event observer.
        if (!$columns->isEmpty() && !$columns->has($attributesNeededToCheckBookmarkHealth = ['id', 'url'])) {
            $columns = new BookmarkAttributes(collect($columns)->push(...$attributesNeededToCheckBookmarkHealth)->unique()->all()); // @phpstan-ignore-line
        }

        return Model::WithQueryOptions($columns)
            ->whereIn('bookmarks.id', $IDs->asIntegers()->unique()->all())
            ->get()
            ->map(function (Model $bookmark) use ($originalRequestedColumns): Bookmark {
                if (!empty($originalRequestedColumns)) {
                    collect($bookmark->toArray())
                        ->keys()
                        ->diff($originalRequestedColumns)
                        ->each(fn (string $attribute) => $bookmark->offsetUnset($attribute));
                }

                return BookmarkBuilder::fromModel($bookmark)->build();
            })->pipeInto(Collection::class);
    }
}
