<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\ResourceIDsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\ValueObjects\ResourceID;
use App\Exceptions\HttpException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderBookmarkRepository;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserID;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use App\Notifications\BookmarksRemovedFromFolderNotification as Notification;
use App\Repositories\NotificationRepository;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private FolderBookmarkRepository $folderBookmarks,
        private FolderPermissionsRepository $permissions,
        private NotificationRepository $notifications
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $folder = $this->repository->find($folderID, Attributes::only('id,user_id,settings'));

        $this->ensureUserCanPerformAction($folder);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->folderBookmarks->remove($folderID, $bookmarkIDs);

        event(new FolderModifiedEvent($folderID));

        $this->notifyFolderOwner($bookmarkIDs, $folder);
    }

    private function ensureUserCanPerformAction(Folder $folder): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource())($folder);
        } catch (SymfonyHttpException $e) {
            $canRemoveBookmarks = $this->permissions
                ->getUserAccessControls(UserID::fromAuthUser(), $folder->folderID)
                ->canRemoveBookmarks();

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

    private function notifyFolderOwner(ResourceIDsCollection $bookmarkIDs, Folder $folder): void
    {
        $collaboratorID = UserID::fromAuthUser();
        $bookmarksWereRemovedByFolderOwner = $collaboratorID->equals($folder->ownerID);
        $notification = new Notification($bookmarkIDs, $folder->folderID, $collaboratorID);

        if (
            $bookmarksWereRemovedByFolderOwner ||
            $folder->settings->notificationsAreDisabled()  ||
            $folder->settings->bookmarksRemovedNotificationIsDisabled()
        ) {
            return;
        }

        $this->notifications->notify($folder->ownerID, $notification);
    }
}
