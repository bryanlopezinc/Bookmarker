<?php

declare(strict_types=1);

namespace Tests;

use App\Collections\ResourceIDsCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;

final class TestBookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /** @var array<int> */
    private static $requestedBookmarkIDs = [];

    public function __construct()
    {
        if (!app()->environment('testing')) {
            throw new \RuntimeException(__CLASS__ . ' can only be used in test enviroments');
        }
    }

    /**
     * Get all the bookmark ids that will be checked by the healthchecker
     *
     * @return array<int>
     */
    public static function requestedBookmarkIDs(): array
    {
        return array_keys(static::$requestedBookmarkIDs);
    }

    public function whereNotRecentlyChecked(ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        $bookmarkIDs->asIntegers()->each(fn (int $bookmarkID) => static::$requestedBookmarkIDs[$bookmarkID] = true);

        return ResourceIDsCollection::fromNativeTypes([]);
    }

    public function update(array $records): void
    {
    }
}