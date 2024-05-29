<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\Enums\FolderBookmarkVisibility;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;

final class CannotDeleteHiddenBookmarksConstraint
{
    /**
     * @param array<Bookmark> $folderBookmarks
     */
    public function __construct(private readonly array $folderBookmarks, private readonly User $authUser)
    {
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->wasCreatedBy($this->authUser)) {
            return;
        }

        foreach ($this->folderBookmarks as $bookmark) {
            $visibility = FolderBookmarkVisibility::from($bookmark->visibility);

            if ($visibility == FolderBookmarkVisibility::PRIVATE) {
                throw new BookmarkNotFoundException();
            }
        }
    }
}
