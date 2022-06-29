<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\Repositories\FoldersRepository;
use App\ValueObjects\ResourceID;

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
        (new EnsureAuthorizedUserOwnsResource)($this->foldersRepository->find($folderID));

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
