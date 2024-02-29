<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\AddBookmarksToFolderRequestData;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;

final class BookmarksExistsConstraint implements FolderRequestHandlerInterface, BookmarksAwareInterface
{
    /** @var array<Bookmark> */
    private array $bookmarks;

    private readonly AddBookmarksToFolderRequestData $data;

    public function __construct(AddBookmarksToFolderRequestData $data)
    {
        $this->data = $data;
    }

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
    public function handle(Folder $folder): void
    {
        if (count($this->data->bookmarkIds) !== count($this->bookmarks)) {
            throw new BookmarkNotFoundException();
        }
    }
}
