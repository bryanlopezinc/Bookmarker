<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Exceptions\HttpException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private FetchFolderBookmarksRepository $folderBookmarks,
        private FolderBookmarkRepository $deleteFolderBookmark
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->deleteFolderBookmark->remove($folderID, $bookmarkIDs);

        event(new FolderModifiedEvent($folderID));
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if (!$this->folderBookmarks->containsAll($bookmarkIDs, $folderID)) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
