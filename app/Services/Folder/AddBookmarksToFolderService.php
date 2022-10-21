<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\BookmarksCollection;
use App\Collections\ResourceIDsCollection as IDs;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\ResourceID;
use App\Exceptions\HttpException as HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserID;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private BookmarkRepository $bookmarksRepository,
        private FolderBookmarkRepository $folderBookmarks,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function add(IDs $bookmarkIDs, ResourceID $folderID, IDs $makeHidden): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,user_id,bookmarks_count'));
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkAttributes::only('user_id,id,url'));

        $this->ensureUserHasPermissionToPerformAction($folder);
        $this->ensureFolderCanContainBookmarks($bookmarkIDs, $folder);
        $this->ensureBookmarksExistAndBelongToUser($bookmarks, $bookmarkIDs);
        $this->ensureFolderDoesNotContainBookmarks($folderID, $bookmarkIDs);

        $this->folderBookmarks->add($folderID, $bookmarkIDs, $makeHidden);

        event(new FolderModifiedEvent($folderID));
        dispatch(new CheckBookmarksHealth(new BookmarksCollection($bookmarks)));
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource)($folder);
        } catch (SymfonyHttpException $e) {
            $canAddBookmarksToFolder = $this->permissions
                ->getUserAccessControls(UserID::fromAuthUser(), $folder->folderID)
                ->canAddBookmarks();

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

    /**
     * @param Collection<Bookmark> $bookmarks
     */
    private function ensureBookmarksExistAndBelongToUser(Collection $bookmarks, IDs $bookmarksToAddToFolder): void
    {
        if ($bookmarks->count() !== $bookmarksToAddToFolder->count()) {
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
