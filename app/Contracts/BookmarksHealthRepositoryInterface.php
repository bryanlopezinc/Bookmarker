<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Collections\ResourceIDsCollection;

interface BookmarksHealthRepositoryInterface
{
    /**
     * Get the bookmark IDs that have not been recently checked
     * or return the ids that have never been checked from the given bookmark IDs.
     */
    public function whereNotRecentlyChecked(ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection;

    /**
     * update the given bookmarks health
     *
     * @param array<int,bool> $records An associative array of health checked data
     *  where each key is the bookmarkID and the value (a bool) indicating if the heathCheck passed or failed.
     */
    public function update(array $records): void;
}
