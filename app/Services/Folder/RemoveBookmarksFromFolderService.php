<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderSettings;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserId;
use App\Notifications\BookmarksRemovedFromFolderNotification as Notification;
use App\Repositories\NotificationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FetchFolderService $repository,
        private FolderPermissionsRepository $permissions,
        private NotificationRepository $notifications
    ) {
    }

    public function remove(array $bookmarkIDs, int $folderID): void
    {
        $authUserId = UserId::fromAuthUser()->value();

        $folder = $this->repository->find($folderID, ['id', 'user_id', 'settings', 'updated_at']);

        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIDs)
            ->whereExists(function (&$query) {
                $query = Bookmark::query()
                    ->whereRaw('id = folders_bookmarks.bookmark_id')
                    ->getQuery();
            })
            ->get();

        $this->ensureUserCanPerformAction($folder, $authUserId);

        $this->ensureBookmarksExistsInFolder($bookmarkIDs, $folderBookmarks);

        $this->delete($folderBookmarks, $folder);

        $this->notifyFolderOwner($bookmarkIDs, $folder, $authUserId);
    }

    private function delete(Collection $folderBookmarks, Folder $folder): void
    {
        $deleted = $folderBookmarks->toQuery()->delete();

        if ($deleted > 0) {
            $folder->updated_at = now();

            $folder->save();
        }
    }

    private function ensureUserCanPerformAction(Folder $folder, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $accessControls = $this->permissions->getUserAccessControls($authUserId, $folder->id);

            if ($accessControls->isEmpty()) {
                throw $e;
            }

            if (!$accessControls->canRemoveBookmarks()) {
                throw new HttpResponseException(
                    response()->json(['message' => 'NoRemoveBookmarksPermission'], 403)
                );
            }
        }
    }

    private function ensureBookmarksExistsInFolder(array $bookmarkIds, Collection $folderBookmarks): void
    {
        if ($folderBookmarks->count() !== count($bookmarkIds)) {
            throw HttpException::notFound(['message' => 'BookmarkNotFound']);
        }
    }

    private function notifyFolderOwner(array $bookmarkIDs, Folder $folder, int $authUserId): void
    {
        $folderSettings = FolderSettings::fromQuery($folder->settings);

        if (
            $authUserId === $folder->user_id ||
            $folderSettings->notificationsAreDisabled()  ||
            $folderSettings->bookmarksRemovedNotificationIsDisabled()
        ) {
            return;
        }

        $this->notifications->notify(
            $folder->user_id,
            new Notification($bookmarkIDs, $folder->id, $authUserId)
        );
    }
}
