<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Collections\ResourceIDsCollection as Collection;
use App\HealthCheckResult;

interface BookmarksHealthRepositoryInterface
{
    /**
     * Get the bookmark IDs that has not been recently checked
     * with the ids that has never been checked from the given bookmark IDs.
     */
    public function whereNotRecentlyChecked(Collection $bookmarkIDs): Collection;

    /**
     * Update the given bookmarks health status.
     *
     * @param array<HealthCheckResult> $records
     */
    public function update(array $records): void;
}
