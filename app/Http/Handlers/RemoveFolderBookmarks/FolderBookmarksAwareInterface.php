<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Models\FolderBookmark;

interface FolderBookmarksAwareInterface
{
    /**
     * @param array<FolderBookmark> $folderBookmarks
     */
    public function setBookmarks(array $folderBookmarks): void;
}
