<?php

declare(strict_types=1);

namespace Tests\Traits;

use Tests\TestBookmarksHealthRepository;
use Illuminate\Testing\Assert as PHPUnit;

trait WillCheckBookmarksHealth
{
    /**
     * @param array<int> $bookmarks
     */
    protected function assertBookmarksHealthWillBeChecked(array $bookmarks): void
    {
        PHPUnit::assertEquals(
            array_diff($bookmarks, TestBookmarksHealthRepository::$bookmarkIds),
            []
        );
    }

    /**
     * @param array<int> $bookmarkIDs
     */
    protected function assertBookmarksHealthWillNotBeChecked(array $bookmarkIDs): void
    {
        PHPUnit::assertEquals(
            array_intersect($bookmarkIDs, TestBookmarksHealthRepository::$bookmarkIds),
            []
        );
    }
}
