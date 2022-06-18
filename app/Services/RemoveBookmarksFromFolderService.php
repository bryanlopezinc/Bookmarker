<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use App\Exceptions\HttpException;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FolderBookmarksRepository $folderBookmarks
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        (new EnsureAuthorizedUserOwnsResource)($this->repository->find($folderID));

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->folderBookmarks->removeBookmarksFromFolder($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->folderBookmarks->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->count() !== $bookmarkIDs->count()) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
