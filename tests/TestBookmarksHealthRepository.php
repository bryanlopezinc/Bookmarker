<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\BookmarksHealthRepositoryInterface;

final class TestBookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /** @var array<int,bool> */
    private static $requestedBookmarkIDs = [];

    public function __construct(private BookmarksHealthRepositoryInterface $baseRepository)
    {
        if (!app()->environment('testing')) {
            throw new \RuntimeException(__CLASS__ . ' can only be used in test environments');
        }
    }

    /**
     * Get all the bookmark ids that will be checked by the health checker
     *
     * @return array<int>
     */
    public static function requestedBookmarkIDs(): array
    {
        return array_keys(static::$requestedBookmarkIDs);
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotRecentlyChecked(array $bookmarkIDs): array
    {
        $whereNotRecentlyChecked = $this->baseRepository->whereNotRecentlyChecked($bookmarkIDs);

        collect($whereNotRecentlyChecked)
            ->each(fn (int $bookmarkID) => static::$requestedBookmarkIDs[$bookmarkID] = true);

        return [];
    }

    public function update(array $records): void
    {
    }
}
