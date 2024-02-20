<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Bookmark;
use App\Models\Folder;

final class BookmarksExistConstraint implements HandlerInterface, BookmarksAwareInterface
{
    /** @var array<Bookmark> */
    private array $bookmarks;

    /**
     * @inheritdoc
     */
    public function setBookmarks(array $bookmarks): void
    {
        $this->bookmarks = $bookmarks;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        if (count($bookmarkIds) !== count($this->bookmarks)) {
            throw AddBookmarksToFolderException::bookmarkDoesNotExist();
        }
    }
}
