<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Exceptions\HttpException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserID;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private FetchFolderBookmarksRepository $folderBookmarks,
        private FolderBookmarkRepository $deleteFolderBookmark,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,user_id'));

        $this->ensureUserCanPerformAction($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->deleteFolderBookmark->remove($folderID, $bookmarkIDs);

        event(new FolderModifiedEvent($folderID));
    }

    private function ensureUserCanPerformAction(Folder $folder): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource)($folder);
        } catch (SymfonyHttpException $e) {
            $canRemoveBookmarks = $this->permissions
                ->getUserPermissionsForFolder(UserID::fromAuthUser(), $folder->folderID)
                ->canRemoveBookmarksFromFolder();

            if (!$canRemoveBookmarks) {
                throw $e;
            }
        }
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        if (!$this->folderBookmarks->containsAll($bookmarkIDs, $folderID)) {
            throw HttpException::notFound(['message' => "Bookmarks does not exists in folder"]);
        }
    }
}
