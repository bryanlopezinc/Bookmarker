<?php

declare(strict_types=1);

namespace Tests\Traits;

use Tests\TestBookmarksHealthRepository;

trait AssertsBookmarksWillBeHealthchecked
{
    /**
     * @param array<int> bookmarks
     */
    protected function assertBookmarksHealthWillBeChecked(array $bookmarks): void
    {
        $checked = TestBookmarksHealthRepository::requestedBookmarkIDs();

        collect($bookmarks)->each(fn (int $bookmarkID) => $this->assertTrue(in_array($bookmarkID, $checked,true)));
    }

    /**
     * @param array<int> bookmarks
     */
    protected function assertBookmarksHealthWillNotBeChecked(array $bookmarks): void
    {
        $checked = TestBookmarksHealthRepository::requestedBookmarkIDs();

        collect($bookmarks)->each(fn (int $bookmarkID) => $this->assertFalse(in_array($bookmarkID, $checked, true)));
    }
}
