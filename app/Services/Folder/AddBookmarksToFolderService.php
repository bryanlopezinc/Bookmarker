<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Folder;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\Folder\FoldersRepository;
use App\Exceptions\HttpException as HttpException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FetchBookmarksRepository $bookmarksRepository,
        private FetchFolderBookmarksRepository $folderBookmarks,
        private FolderBookmarkRepository $createFolderBookmark
    ) {
    }

    public function add(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID, ResourceIDsCollection $makeHidden): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,userId,storage'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureFolderCanContainBookmarks($bookmarkIDs, $folder);

        $this->validateBookmarks($bookmarkIDs);

        $this->checkFolderForPossibleDuplicates($folderID, $bookmarkIDs);

        $this->createFolderBookmark->add($folderID, $bookmarkIDs, $makeHidden);
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
