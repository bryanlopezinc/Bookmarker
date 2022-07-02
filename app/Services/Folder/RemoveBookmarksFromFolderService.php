<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use App\Exceptions\HttpException;
use App\QueryColumns\FolderAttributes as Attributes;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FolderBookmarksRepository $folderBookmarks
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,userId'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->folderBookmarks->removeBookmarksFromFolder($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if (!$this->folderBookmarks->containsAll($bookmarkIDs, $folderID)) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
