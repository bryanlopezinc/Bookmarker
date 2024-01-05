<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Enums\FolderVisibility;
use App\Enums\Permission;
use App\Exceptions\FolderActionDisabledException;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\FolderNotModifiedAfterOperationException;
use App\Exceptions\HttpException;
use App\Exceptions\PermissionDeniedException;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Models\Scopes\DisabledActionScope;
use App\Models\Scopes\UserIsCollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\FolderUpdatedNotification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use Illuminate\Support\Facades\Hash;
use App\Repositories\NotificationRepository;
use Illuminate\Auth\AuthenticationException;
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
        /** @var User */
        $authUser = auth()->user();

        $folder = Folder::select(['id', 'user_id', 'name', 'description', 'visibility', 'settings'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new DisabledActionScope(Permission::UPDATE_FOLDER))
            ->tap(new UserIsCollaboratorScope($authUser->getAuthIdentifier()))
            //we could query for collaborators count but no need to count
            //rows when we could do a simple select.
            ->addSelect([
                'hasCollaborators' => FolderCollaborator::query()
                    ->select('id')
                    ->whereColumn('folder_id', 'folders.id')
                    ->whereExists(User::whereRaw('id = folders_collaborators.collaborator_id'))
                    ->limit(1)
            ])
            ->find($request->route('folder_id'));

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        $this->ensureUserHasPermissionToUpdateFolder($folder, $request, $authUser->getAuthIdentifier());

        $this->ensureCanChangeVisibility($folder, $request);

        $this->confirmPasswordBeforeMakingPrivateFolderPublic($request, $authUser, $folder);

        $this->notifyFolderOwner($this->performUpdate($request, $folder), $authUser->getAuthIdentifier());
    }

    private function ensureCanChangeVisibility(Folder $folder, Request $request): void
    {
        if ($request->missing('visibility')) {
            return;
        }

        $newVisibility = FolderVisibility::fromRequest($request);

        if ($folder->hasCollaborators && $newVisibility->isPrivate()) {
            throw HttpException::forbidden(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
        }

        if ($folder->hasCollaborators && $newVisibility->isPasswordProtected()) {
            throw HttpException::forbidden(['message' => 'CannotMakeFolderWithCollaboratorsPasswordProtected']);
        }
    }

    private function ensureUserHasPermissionToUpdateFolder(Folder $folder, Request $request, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            throw_if(!$folder->userIsCollaborator, $e);

            if ($request->has('visibility')) {
                throw HttpException::forbidden(['message' => 'NoUpdatePrivacyPermission']);
            }

            if (!$this->permissions->all($authUserId, $folder->id)->canUpdateFolder()) {
                throw new PermissionDeniedException(Permission::UPDATE_FOLDER);
            }

            if ($folder->actionIsDisable) {
                throw new FolderActionDisabledException(Permission::UPDATE_FOLDER);
            }
        }
    }

    private function performUpdate(Request $request, Folder $folder): Folder
    {
        $newVisibility = FolderVisibility::fromRequest($request);

        $isUpdatingFolderPassword = $request->has('folder_password') && $request->missing('visibility');

        if ($isUpdatingFolderPassword && !$folder->visibility->isPasswordProtected()) {
            throw new HttpException(['message' => 'FolderNotPasswordProtected'], 400);
        }

        if ($request->has('name')) {
            $folder->name = $request->validated('name');
        }

        if ($request->has('description')) {
            $folder->description = $request->validated('description');
        }

        if ($request->has('visibility')) {
            $folder->visibility = $newVisibility;
        }

        if ($isUpdatingFolderPassword || $newVisibility->isPasswordProtected()) {
            $folder->password = $request->validated('folder_password');
        }

        if (!$folder->isDirty()) {
            throw new FolderNotModifiedAfterOperationException();
        }

        $updatedFolder = clone $folder;

        $folder->save();

        return $updatedFolder;
    }

    private function confirmPasswordBeforeMakingPrivateFolderPublic(Request $request, User $authUser, Folder $folder): void
    {
        $newVisibility = FolderVisibility::fromRequest($request);

        $isMakingPrivateFolderPublic = $newVisibility->isPublic() && $request->has('visibility') && $folder->visibility->isPrivate();

        if (!$isMakingPrivateFolderPublic) {
            return;
        }

        if ($request->missing('password')) {
            throw ValidationException::withMessages(['password' => 'The Password field is required for this action.']);
        }

        if (!Hash::check($request->input('password'), $authUser->password)) {
            throw new AuthenticationException('InvalidPassword');
        }
    }

    private function notifyFolderOwner(Folder $folder, int $authUserId): void
    {
        $settings = $folder->settings;
        $notifications = [];

        if (
            $folder->user_id === $authUserId ||
            $settings->notificationsAreDisabled() ||
            $settings->folderUpdatedNotificationIsDisabled()
        ) {
            return;
        }

        foreach (array_keys($folder->getDirty()) as $modified) {
            $notifications[] = match ($modified) {
                'name'        => new FolderUpdatedNotification($folder, $authUserId, $modified),
                'description' => new FolderUpdatedNotification($folder, $authUserId, $modified),
                default => throw new \Exception("Cannot create notification for [$modified]")
            };
        }

        foreach ($notifications as $notification) {
            $this->notifications->notify($folder->user_id, $notification);
        }
    }
}
