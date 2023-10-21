<?php

declare(strict_types=1);

namespace App\Contracts;

use App\HealthCheckResult;

interface BookmarksHealthRepositoryInterface
{
    /**
     * Get the bookmark IDs that has not been recently checked
     * with the ids that has never been checked from the given bookmark IDs.
     *
     * @param array<int> $bookmarkIDs
     *
     * @return array<int>
     */
    public function whereNotRecentlyChecked(array $bookmarkIDs): array;

    /**
     * Update the given bookmarks health status.
     *
     * @param array<HealthCheckResult> $records
     */
    public function update(array $records): void;
}
