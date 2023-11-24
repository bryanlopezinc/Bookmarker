<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\BookmarksHealthRepositoryInterface;
use App\HealthCheckResult;
use App\Models\BookmarkHealth;

final class BookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function whereNotRecentlyChecked(array $bookmarkIDs): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection */
        $bookmarksHealths = BookmarkHealth::whereIn('bookmark_id', $bookmarkIDs)->get(['bookmark_id', 'last_checked']);

        $notCheckedRecently = $bookmarksHealths->where(
            'last_checked',
            '<=',
            now()->subDays(setting('HEALTH_CHECK_FREQUENCY'))
        );

        //The bookmarkIDs that does not exists in the database.
        $missingRecords = collect($bookmarkIDs)->diff($bookmarksHealths->pluck('bookmark_id'));

        return $notCheckedRecently
            ->pluck('bookmark_id')
            ->merge($missingRecords)
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $records): void
    {
        $updateData = array_map(
            array: $records,
            callback: function (HealthCheckResult $result) {
                return [
                    'status_code' => $result->response->status(),
                    'bookmark_id' => $result->bookmarkID
                ];
            }
        );

        BookmarkHealth::query()->upsert($updateData, 'bookmark_id', ['status_code']);
    }
}
