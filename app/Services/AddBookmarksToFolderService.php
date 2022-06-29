<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Folder;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use App\Exceptions\HttpException as HttpException;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FetchBookmarksRepository $bookmarksRepository,
        private FolderBookmarksRepository $folderBookmarks
    ) {
    }

    public function add(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID, ResourceIDsCollection $makeHidden): void
    {
        (new EnsureAuthorizedUserOwnsResource)($folder = $this->repository->find($folderID));

        $this->ensureFolderCanContainBookmarks($bookmarkIDs, $folder);

        $this->validateBookmarks($bookmarkIDs);

        $this->checkFolderForPossibleDuplicates($folderID, $bookmarkIDs);

        $this->folderBookmarks->add($folderID, $bookmarkIDs, $makeHidden);
    }

    private function ensureFolderCanContainBookmarks(ResourceIDsCollection $bookmarks, Folder $folder): void
    {
        $exceptionMessage = $folder->storage->isFull()
            ? 'folder cannot contain more bookmarks'
            : sprintf('folder can only take only %s more bookmarks', $folder->storage->spaceAvailable());

        if (!$folder->storage->canContain($bookmarks)) {
            throw HttpException::forbidden(['message' => $exceptionMessage]);
        }
    }

    private function validateBookmarks(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkAttributes::only('userId,id'));

        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            throw HttpException::notFound(['message' => 'The bookmarks does not exists']);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);
    }

    private function checkFolderForPossibleDuplicates(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if ($this->folderBookmarks->contains($bookmarkIDs, $folderID)) {
            throw HttpException::conflict(['message' => 'Bookmarks already exists']);
        }
    }
}
