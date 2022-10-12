<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection as IDs;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Exceptions\HttpException as HttpException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserID;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private FetchBookmarksRepository $bookmarksRepository,
        private FetchFolderBookmarksRepository $folderBookmarks,
        private FolderBookmarkRepository $createFolderBookmark,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function add(IDs $bookmarkIDs, ResourceID $folderID, IDs $makeHidden): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,user_id,bookmarks_count'));

        $this->ensureUserHasPermissionToPerformAction($folder);
        $this->ensureFolderCanContainBookmarks($bookmarkIDs, $folder);
        $this->ensureBookmarksExistAndBelongToUser($bookmarkIDs);
        $this->ensureFolderDoesNotContainBookmarks($folderID, $bookmarkIDs);

        $this->createFolderBookmark->add($folderID, $bookmarkIDs, $makeHidden);

        event(new FolderModifiedEvent($folderID));
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource)($folder);
        } catch (SymfonyHttpException $e) {
            $canAddBookmarksToFolder = $this->permissions
                ->getUserPermissionsForFolder(UserID::fromAuthUser(), $folder->folderID)
                ->canAddBookmarksToFolder();

            if (!$canAddBookmarksToFolder) {
                throw $e;
            }
        }
    }

    private function ensureFolderCanContainBookmarks(IDs $bookmarks, Folder $folder): void
    {
        $exceptionMessage = $folder->storage->isFull()
            ? 'folder cannot contain more bookmarks'
            : sprintf('folder can only take only %s more bookmarks', $folder->storage->spaceAvailable());

        if (!$folder->storage->canContain($bookmarks)) {
            throw HttpException::forbidden(['message' => $exceptionMessage]);
        }
    }

    private function ensureBookmarksExistAndBelongToUser(IDs $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkAttributes::only('user_id,id'));

        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            throw HttpException::notFound(['message' => 'The bookmarks does not exists']);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);
    }

    private function ensureFolderDoesNotContainBookmarks(ResourceID $folderID, IDs $bookmarkIDs): void
    {
        if ($this->folderBookmarks->contains($bookmarkIDs, $folderID)) {
            throw HttpException::conflict(['message' => 'Bookmarks already exists']);
        }
    }
}
