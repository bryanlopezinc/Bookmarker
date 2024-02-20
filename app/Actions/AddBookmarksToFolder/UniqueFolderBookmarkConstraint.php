<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Folder;
use App\Models\FolderBookmark;

final class UniqueFolderBookmarkConstraint implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        $hasBookmarks = FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIds)
            ->count() > 0;

        if ($hasBookmarks) {
            throw AddBookmarksToFolderException::bookmarksAlreadyExists();
        }
    }
}
