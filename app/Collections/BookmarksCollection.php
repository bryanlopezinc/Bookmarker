<?php

declare(strict_types=1);

namespace App\Collections;

use App\DataTransferObjects\Bookmark;

final class BookmarksCollection extends BaseCollection
{
    protected function isValid(mixed $item): bool
    {
        return $item instanceof Bookmark;
    }

    public function ids(): ResourceIDsCollection
    {
        return $this->collection->map(fn (Bookmark $bookmark) => $bookmark->id)->pipeInto(ResourceIDsCollection::class);
    }

    /**
     * @param callable(Bookmark, int): bool $callback
     */
    public function filter(callable $callback): BookmarksCollection
    {
        return $this->collection->filter($callback)->pipeInto(self::class);
    }

    /**
     * Get only the bookmarks with given ids
     */
    public function filterByIDs(ResourceIDsCollection $collection): BookmarksCollection
    {
        $ids = $collection->asIntegers()->all();

        return $this->collection->filter(fn (Bookmark $bookmark) => in_array($bookmark->id->value(), $ids, true))->pipeInto(self::class);
    }
}
