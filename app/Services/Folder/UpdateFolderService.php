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
use App\Models\FolderCollaborator;
use App\Models\Scopes\DisabledActionScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\FolderUpdatedNotification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use Illuminate\Support\Facades\Hash;
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
        $folder = Folder::onlyAttributes(['id', 'user_id', 'name', 'description', 'visibility', 'settings',])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledActionScope(Permission::UPDATE_FOLDER))
            //we could query for collaborators count but no need to count
            //rows when we could do a simple select.
            ->addSelect([
                'hasCollaborators' => FolderCollaborator::query()
                    ->select('id')
                    ->whereColumn('folder_id', 'folders.id')
                    ->limit(1)
            ])
            ->find($request->integer('folder'));

        FolderNotFoundException::throwIf(!$folder);

        $authUser = auth()->user();

        $this->ensureUserCanUpdateFolder($folder, $request, $authUser->getAuthIdentifier());

        $this->ensureNoVisibilityConflict($folder, $request);

        $this->confirmPasswordBeforeUpdatingPrivacy($request, $authUser);

        $updatedFolder = $this->performUpdate($request, $folder);

        $this->notifyFolderOwner($updatedFolder, $authUser->getAuthIdentifier());
    }

    private function ensureNoVisibilityConflict(Folder $folder, Request $request): void
    {
        if ($request->missing('visibility')) {
            return;
        }

        if (FolderVisibility::fromRequest($request) == $folder->visibility) {
            throw HttpException::conflict(['message' => 'DuplicateVisibilityState']);
        }
    }

    private function ensureUserCanUpdateFolder(Folder $folder, Request $request, int $authUserId): void
    {
        $folderBelongsToAuthUser = $folder->user_id === auth()->id();

        try {
            FolderNotFoundException::throwIf(!$folderBelongsToAuthUser);

            if ($folder->hasCollaborators && FolderVisibility::fromRequest($request)->isPrivate()) {
                throw HttpException::forbidden(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
            }
        } catch (FolderNotFoundException $e) {
            $userPermissions = $this->permissions->all($authUserId, $folder->id);

            if ($userPermissions->isEmpty()) {
                throw $e;
            }

            if ($request->has('visibility')) {
                throw HttpException::forbidden(['message' => 'NoUpdatePrivacyPermission']);
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
        if ($request->missing('visibility') || $request->input('visibility') !== 'public') {
            return;
        }

        if (!Hash::check($request->input('password'), $authUser->getAuthPassword())) {
            throw HttpException::forbidden(['message' => 'InvalidPassword']);
        }
    }

    private function notifyFolderOwner(Folder $folder, int $authUserId): void
    {
        $settings = $folder->settings;
        $notifications = [];

        if ($folder->user_id === $authUserId) {
            return;
        }

        if (
            $settings->notificationsAreDisabled() ||
            $settings->folderUpdatedNotificationIsDisabled()
        ) {
            return;
        }

        foreach (array_keys($folder->getDirty()) as $modified) {
            $notifications[] = match ($modified) {
                'name'        => new FolderUpdatedNotification($folder, $authUserId, $modified),
                'description' => new FolderUpdatedNotification($folder, $authUserId, $modified),
            };
        }

        foreach ($notifications as $notification) {
            $this->notifications->notify($folder->user_id, $notification);
        }
    }
}
