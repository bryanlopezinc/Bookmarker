<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class UserOwnsBookmarksConstraint
{
    /**
     * @param array<Bookmark> $bookmarks
     */
    public function __construct(private readonly Data $data, private readonly array $bookmarks)
    {
    }

    public function __invoke(Folder $folder): void
    {
        foreach ($this->bookmarks as $bookmark) {
            if ($bookmark->user_id !== $this->data->authUser->id) {
                throw new BookmarkNotFoundException();
            }
        }
    }
}
