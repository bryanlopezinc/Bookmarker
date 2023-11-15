<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\FolderBookmarkVisibility;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Bookmark;
use App\Models\FolderBookmark;
use App\Repositories\BookmarkRepository;

final class HideFolderBookmarksService
{
    public function __construct(
        private FetchFolderService $folderRepository,
        private BookmarkRepository $bookmarkRepository
    ) {
    }

    public function hide(array $bookmarkIDs, int $folderID): void
    {
        $folder = $this->folderRepository->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->ensureCannotHideCollaboratorBookmarks($bookmarkIDs);

        FolderBookmark::query()
            ->where('folder_id', $folderID)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIDs)
            ->update(['visibility' => FolderBookmarkVisibility::PRIVATE->value]);
    }

    private function ensureCannotHideCollaboratorBookmarks(array $bookmarkIDs): void
    {
        try {
            $this->bookmarkRepository->findManyById($bookmarkIDs, ['user_id'])
                ->each(fn (Bookmark $bookmark) => BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark));
        } catch (BookmarkNotFoundException) {
            throw HttpException::forbidden(['message' => 'CannotHideCollaboratorBookmarks']);
        }
    }

    private function ensureBookmarksExistsInFolder(int $folderID, array $bookmarkIDs): void
    {
        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folderID)
            ->whereIn('bookmark_id', $bookmarkIDs)
            ->whereExists(function (&$query) {
                $query = Bookmark::query()
                    ->whereRaw('id = folders_bookmarks.bookmark_id')
                    ->getQuery();
            })
            ->get(['id']);

        if ($folderBookmarks->count() !== count($bookmarkIDs)) {
            throw new BookmarkNotFoundException();
        }
    }
}
