<?php

declare(strict_types=1);

namespace Tests\Traits;

use Tests\TestBookmarksHealthRepository;

trait WillCheckBookmarksHealth
{
    /**
     * @param array<int> $bookmarks
     */
    protected function assertBookmarksHealthWillBeChecked(array $bookmarks): void
    {
        $checked = TestBookmarksHealthRepository::requestedBookmarkIDs();

        collect($bookmarks)->each(fn (int $bookmarkID) => $this->assertTrue(in_array($bookmarkID, $checked, true)));
    }

    /**
     * @param array<int> $bookmarkIDs
     */
    protected function assertBookmarksHealthWillNotBeChecked(array $bookmarkIDs): void
    {
        $checked = TestBookmarksHealthRepository::requestedBookmarkIDs();

        collect($bookmarkIDs)->each(fn (int $bookmarkID) => $this->assertFalse(in_array($bookmarkID, $checked, true)));
    }
}
