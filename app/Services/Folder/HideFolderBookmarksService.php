<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;

final class HideFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $repository,
        private FolderRepositoryInterface $folderRepository,
        private FolderBookmarkRepository $createFolderBookmark
    ) {
    }

    public function hide(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->folderRepository->find($folderID, Attributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->createFolderBookmark->makeHidden($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if (!$this->repository->containsAll($bookmarkIDs, $folderID)) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
