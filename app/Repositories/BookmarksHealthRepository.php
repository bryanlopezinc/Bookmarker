<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\BookmarkHealth;
use App\ValueObjects\ResourceID;

final class BookmarksHealthRepository
{
    /** frequency in days a bookmarks health should be checked */
    private const CHECK_FREQUENCY = 6;

    /**
     * Get the bookmark IDs that have not been recently checked
     * or return the ids that have never been checked from the given bookmark IDs.
     */
    public function whereNotRecentlyChecked(ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        $foundBookmarkIDs = collect();

        $bookmarks = BookmarkHealth::whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())->get(['bookmark_id', 'last_checked']);

        $foundBookmarkIDs->push(...$bookmarks->pluck('bookmark_id')->all());

        return $bookmarks->where('last_checked', '<=', now()->subDays(self::CHECK_FREQUENCY))
            ->pluck('bookmark_id')
            ->merge($bookmarkIDs->asIntegers()->diff($foundBookmarkIDs)) // merge bookmark ids that have never been checked.
            ->map(fn (int $id) => new ResourceID($id))
            ->pipeInto(ResourceIDsCollection::class);
    }

    /**
     * @param array<int,bool> $records An associative array of health check data
     *  where each key is the bookmarkID and the value a bool indicationg if the heathCheck passed or failed.
     */
    public function update(array $records): void
    {
        $time = now();

        BookmarkHealth::whereIn('bookmark_id', array_keys($records))->delete();

        $data = collect($records)->map(fn (bool $isHealthy, int $bookmarkID) => [
            'bookmark_id' => $bookmarkID,
            'is_healthy' => $isHealthy,
            'last_checked' => $time
        ]);

        BookmarkHealth::insert($data->all());
    }
}
