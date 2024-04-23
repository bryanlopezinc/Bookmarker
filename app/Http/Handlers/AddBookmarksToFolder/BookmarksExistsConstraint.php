<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;

final class BookmarksExistsConstraint
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(private readonly Data $data, private readonly array $bookmarks)
    {
    }

    public function __invoke(Folder $folder): void
    {
        if (count($this->data->bookmarksPublicIds) !== count($this->bookmarks)) {
            throw new BookmarkNotFoundException();
        }
    }
}
