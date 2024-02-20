<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Jobs\CheckBookmarksHealth;

final class CheckBookmarksHealthHandler implements HandlerInterface, BookmarksAwareInterface
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
        dispatch(new CheckBookmarksHealth($this->bookmarks));
    }
}
