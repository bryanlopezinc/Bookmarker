<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class UserOwnsBookmarksConstraint implements FolderRequestHandlerInterface, BookmarksAwareInterface
{
    /** @var array<Bookmark> */
    private array $bookmarks;

    public function __construct(private readonly Data $data)
    {
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
        foreach ($this->bookmarks as $bookmark) {
            if ($bookmark->user_id !== $this->data->authUser->id) {
                throw new BookmarkNotFoundException();
            }
        }
    }
}
