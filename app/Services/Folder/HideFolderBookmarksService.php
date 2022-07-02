<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\Repositories\FoldersRepository;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;

final class HideFolderBookmarksService
{
    public function __construct(
        private FolderBookmarksRepository $repository,
        private FoldersRepository $foldersRepository,
        private FetchBookmarksRepository $bookmarksRepository
    ) {
    }

    public function hide(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->foldersRepository->find($folderID, Attributes::only('id,userId'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->repository->makeHidden($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if (!$this->repository->containsAll($bookmarkIDs, $folderID)) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
