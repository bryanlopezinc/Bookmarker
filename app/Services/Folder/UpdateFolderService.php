<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\FolderVisibility;
use App\Enums\Permission;
use App\Exceptions\FolderActionDisabledException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\PermissionDeniedException;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Models\Folder;
use App\Models\Scopes\DisabledActionScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use Illuminate\Support\Facades\Hash;
use App\Notifications\FolderUpdatedNotification as Notification;
use App\Repositories\NotificationRepository;
use Illuminate\Validation\ValidationException;

final class UpdateFolderService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissions,
        private NotificationRepository $notifications,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws HttpException
     */
    public function fromRequest(Request $request): void
    {
        $folder = Folder::onlyAttributes(['id', 'user_id', 'name', 'description', 'visibility', 'settings'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledActionScope(Permission::UPDATE_FOLDER))
            ->find($request->integer('folder'));

        FolderNotFoundException::throwIf(!$folder);

        $authUser = auth()->user();

        $this->ensureUserCanUpdateFolder($folder, $request, $authUser->getAuthIdentifier());

        $this->confirmPasswordBeforeUpdatingPrivacy($request, $authUser);

        $this->ensureNoVisibilityConflict($folder, $request);

        $updatedFolder = $this->performUpdate($request, $folder);

        $this->notifyFolderOwner($updatedFolder, $authUser->getAuthIdentifier());
    }

    private function ensureNoVisibilityConflict(Folder $folder, Request $request): void
    {
        if ($request->missing('visibility')) {
            return;
        }

        $exception = HttpException::conflict(['message' => 'DuplicateVisibilityState']);

        $visibility = $request->input('visibility');

        $folderVisibility = $folder->visibility;

        if ($visibility === 'public' && $folderVisibility->isPublic()) {
            throw $exception;
        }

        if ($visibility === 'private' && $folderVisibility->isPrivate()) {
            throw $exception;
        }
    }

    private function ensureUserCanUpdateFolder(Folder $folder, Request $request, int $authUserId): void
    {
        $folderBelongsToAuthUser = $folder->user_id === auth()->id();

        try {
            FolderNotFoundException::throwIf(!$folderBelongsToAuthUser);
        } catch (FolderNotFoundException $e) {
            $userPermissions = $this->permissions->all($authUserId, $folder->id);

            if ($userPermissions->isEmpty()) {
                throw $e;
            }

            if ($request->has('visibility')) {
                throw HttpException::forbidden(['message' => 'NoUpdatePrivacyPermission']);
            }

            if ($request->has('discussion_mode')) {
                throw HttpException::forbidden(['message' => 'NoUpdateDiscussionStatePermission']);
            }

            if (!$userPermissions->canUpdateFolder()) {
                throw new PermissionDeniedException(Permission::UPDATE_FOLDER);
            }

            if ($folder->actionIsDisable) {
                throw new FolderActionDisabledException(Permission::UPDATE_FOLDER);
            }
        }
    }

    private function performUpdate(Request $request, Folder $folder): Folder
    {
        if ($request->has('name')) {
            $folder->name = $request->input('name');
        }

        if ($request->has('description')) {
            $folder->description = $request->input('description');
        }

        if ($request->has('visibility')) {
            $folder->visibility = FolderVisibility::fromRequest($request);
        }

        $updatedFolder = clone $folder;

        $folder->save();

        return $updatedFolder;
    }

    private function confirmPasswordBeforeUpdatingPrivacy(Request $request, User $authUser): void
    {
        if ($request->missing('visibility') || $request->input('visibility') === 'private') {
            return;
        }

        if (!Hash::check($request->input('password'), $authUser->getAuthPassword())) {
            throw HttpException::forbidden(['message' => 'InvalidPassword']);
        }
    }

    private function notifyFolderOwner(Folder $folder, int $authUserId): void
    {
        if ($folder->user_id === $authUserId) {
            return;
        }

        $settings = $folder->settings;

        if (
            $settings->notificationsAreDisabled() ||
            $settings->folderUpdatedNotificationIsDisabled()
        ) {
            return;
        }

        $this->notifications->notify(
            $folder->user_id,
            new Notification($folder, $authUserId)
        );
    }
}
