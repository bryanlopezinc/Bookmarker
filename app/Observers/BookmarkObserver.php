<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;

final class BookmarkObserver
{
    /** @var array<int,Bookmark>*/
    private static array $cache = [];

    public function retrieved(Bookmark $bookmark): void
    {
        //Check if attributes needed for bookmark health check where retrieved.
        if (!collect($bookmark->toArray())->has(['id', 'url'])) {
            return;
        }

        static::$cache[$bookmark->id] =  BookmarkBuilder::fromModel($bookmark)->build();
    }

    public function deleting(Bookmark $bookmark): void
    {
        unset(static::$cache[$bookmark->id]);
    }

    /**
     * @return array<Bookmark>
     */
    public function getRetrievedBookmarks(): array
    {
        return array_values(static::$cache);
    }
}
