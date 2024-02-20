<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\Permission;
use App\Exceptions\FolderActionDisabledException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\PermissionDeniedException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use App\Models\Scopes\DisabledFeatureScope;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Notifications\BookmarksRemovedFromFolderNotification as Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification as NotificationSender;

final class RemoveFolderBookmarksService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function remove(array $bookmarkIDs, int $folderID): void
    {
        /** @var User */
        $authUser = auth()->user();

        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings', 'updated_at', 'name'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledFeatureScope(Permission::DELETE_BOOKMARKS))
            ->tap(new IsMutedCollaboratorScope($authUser->id))
            ->find($folderID);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        $folderBookmarks = FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIDs)
            ->whereExists(function (&$query) {
                $query = Bookmark::query()
                    ->whereRaw('id = folders_bookmarks.bookmark_id')
                    ->getQuery();
            })
            ->get();

        $this->ensureUserCanPerformAction($folder, $authUser->id);

        $this->ensureBookmarksExistsInFolder($bookmarkIDs, $folderBookmarks);

        $this->delete($folderBookmarks, $folder);

        $this->notifyFolderOwner($bookmarkIDs, $folder, $authUser);
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
        $folderBelongsToAuthUser = $folder->user_id === auth()->id();

        try {
            FolderNotFoundException::throwIf(!$folderBelongsToAuthUser);
        } catch (FolderNotFoundException $e) {
            $accessControls = $this->permissions->all($authUserId, $folder->id);

            if ($accessControls->isEmpty()) {
                throw $e;
            }

            if ($folder->featureIsDisabled) {
                throw new FolderActionDisabledException(Permission::DELETE_BOOKMARKS);
            }

            if (!$accessControls->canRemoveBookmarks()) {
                throw new PermissionDeniedException(Permission::DELETE_BOOKMARKS);
            }
        }
    }

    private function ensureBookmarksExistsInFolder(array $bookmarkIds, Collection $folderBookmarks): void
    {
        if ($folderBookmarks->count() !== count($bookmarkIds)) {
            throw HttpException::notFound(['message' => 'BookmarkNotFound']);
        }
    }

    private function notifyFolderOwner(array $bookmarkIDs, Folder $folder, User $authUser): void
    {
        $folderSettings = $folder->settings;

        if (
            $authUser->id === $folder->user_id                          ||
            $folderSettings->notificationsAreDisabled()               ||
            $folderSettings->bookmarksRemovedNotificationIsDisabled() ||
            $folder->collaboratorIsMuted
        ) {
            return;
        }

        NotificationSender::send(
            new User(['id' => $folder->user_id]),
            new Notification($bookmarkIDs, $folder, $authUser)
        );
    }
}
