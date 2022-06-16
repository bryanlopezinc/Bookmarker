<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Contracts\BookmarksHealthRepositoryInterface;
use App\Models\BookmarkHealth;

final class BookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /** frequency in days a bookmarks health should be checked */
    private const CHECK_FREQUENCY = 6;

    /**
     * {@inheritdoc}
     */
    public function whereNotRecentlyChecked(ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        $foundBookmarkIDs = collect();

        $bookmarks = BookmarkHealth::whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())->get(['bookmark_id', 'last_checked']);

        $foundBookmarkIDs->push(...$bookmarks->pluck('bookmark_id')->all());

        return ResourceIDsCollection::fromNativeTypes(
            $bookmarks->where('last_checked', '<=', now()->subDays(self::CHECK_FREQUENCY))
                ->pluck('bookmark_id')
                ->merge($bookmarkIDs->asIntegers()->diff($foundBookmarkIDs)) // merge bookmark ids that have never been checked.
        );
    }

    /**
     * {@inheritdoc}
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
