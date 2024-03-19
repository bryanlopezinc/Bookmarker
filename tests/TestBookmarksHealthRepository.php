<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\BookmarksHealthRepositoryInterface;
use RuntimeException;

final class TestBookmarksHealthRepository implements BookmarksHealthRepositoryInterface
{
    /** @var array<int> */
    public static array $bookmarkIds = [];

    public function __construct(private BookmarksHealthRepositoryInterface $baseRepository)
    {
        if ( ! app()->environment('testing')) {
            throw new RuntimeException(__CLASS__ . ' can only be used in test environments');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotRecentlyChecked(array $bookmarkIDs): array
    {
        $whereNotRecentlyChecked = $this->baseRepository->whereNotRecentlyChecked($bookmarkIDs);

        foreach ($whereNotRecentlyChecked as $bookmarkId) {
            self::$bookmarkIds[] = $bookmarkId;
        }

        return [];
    }

    public function update(array $records): void
    {
    }
}
