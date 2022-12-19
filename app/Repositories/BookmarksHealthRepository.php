<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\HealthCheckResult;
use App\Models\BookmarkHealth;
use App\ValueObjects\ResourceID;
use Illuminate\Support\Collection;

final class BookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function whereNotRecentlyChecked(ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        $bookmarkIDs = $bookmarkIDs->asIntegers();

        /** @var \Illuminate\Database\Eloquent\Collection */
        $bookmarksHealths = BookmarkHealth::whereIn('bookmark_id', $bookmarkIDs)->get(['bookmark_id', 'last_checked']);

        $notCheckedRecently = $bookmarksHealths->where(
            'last_checked',
            '<=',
            now()->subDays(setting('HEALTH_CHECK_FREQUENCY'))
        );

        //The bookmarkIDs that does not exists in the database.
        $missingRecords = $bookmarkIDs->diff($bookmarksHealths->pluck('bookmark_id'));

        return $notCheckedRecently
            ->pluck('bookmark_id')
            ->merge($missingRecords)
            ->map(fn (int $bookmarkID) => new ResourceID($bookmarkID))
            ->pipeInto(ResourceIDsCollection::class);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $records): void
    {
        $lastChecked = now();

        collect($records)
            ->tap(function (Collection $collection) {
                $bookmarkIDs = $collection->map(fn (HealthCheckResult $result) => $result->bookmarkID->value())->all();

                BookmarkHealth::whereIn('bookmark_id', $bookmarkIDs)->delete();
            })
            ->map(fn (HealthCheckResult $result) => [
                'bookmark_id' => $result->bookmarkID->value(),
                'is_healthy' => $result->response->status() !== 404,
                'last_checked' => $lastChecked
            ])
            ->tap(fn (Collection $collection) => BookmarkHealth::insert($collection->all()));
    }
}
