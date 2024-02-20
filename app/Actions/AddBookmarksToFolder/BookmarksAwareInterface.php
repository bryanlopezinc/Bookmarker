<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Bookmark;

interface BookmarksAwareInterface
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function setBookmarks(array $bookmarks): void;
}
