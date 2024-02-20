<?php

declare(strict_types=1);

namespace App\Actions\AddBookmarksToFolder;

use App\Exceptions\AddBookmarksToFolderException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;

final class UserOwnsBookmarksConstraint implements HandlerInterface, BookmarksAwareInterface
{
    /** @var array<Bookmark> */
    private array $bookmarks;
    private readonly User $authUser;

    public function __construct(User $authUser)
    {
        $this->authUser = $authUser;
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
    public function handle(Folder $folder, array $bookmarkIds): void
    {
        foreach ($this->bookmarks as $bookmark) {
            if ($bookmark->user_id !== $this->authUser->id) {
                throw AddBookmarksToFolderException::bookmarkDoesNotBelongToUser();
            }
        }
    }
}
