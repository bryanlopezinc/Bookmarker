<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use App\ValueObjects\UserId;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RevokeFolderCollaboratorPermissionsService
{
    public function __construct(private FolderPermissionsRepository $permissions)
    {
    }

    public function revokePermissions(int $collaboratorID, int $folderID, UAC $revokePermissions): void
    {
        $folder = Folder::query()->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        $collaboratorPermissions = $this->permissions->getUserAccessControls($collaboratorID, $folderID);

        $this->ensureIsNotPerformingActionOnSelf($collaboratorID, $authUserId = UserId::fromAuthUser()->value());

        $this->ensureUserHasPermissionToPerformAction($folder, $authUserId);

        $this->ensureUserIsACollaborator($collaboratorPermissions);

        $this->ensureCollaboratorHasPermissions($collaboratorPermissions, $revokePermissions);

        FolderCollaboratorPermission::query()
            ->where('folder_id', $folderID)
            ->where('user_id', $collaboratorID)
            ->whereIn('permission_id', FolderPermission::select('id')->whereIn('name', $revokePermissions->permissions))
            ->delete();
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $userFolderAccess = $this->permissions->getUserAccessControls($authUserId, $folder->id);

            if ($userFolderAccess->isEmpty()) {
                throw $e;
            }

            if (!$userFolderAccess->canAddBookmarks()) {
                throw new HttpResponseException(
                    response()->json(['message' => 'NoRevokePermissionPermission'], 403)
                );
            }
        }
    }

    private function ensureIsNotPerformingActionOnSelf(int $collaboratorID, int $authUserId): void
    {
        if ($authUserId === $collaboratorID) {
            throw HttpException::forbidden([
                'message' => 'CannotRemoveSelf'
            ]);
        }
    }

    private function ensureUserIsACollaborator(UAC $collaboratorsCurrentPermissions): void
    {
        if ($collaboratorsCurrentPermissions->isEmpty()) {
            throw HttpException::notFound([
                'message' => 'UserNotACollaborator'
            ]);
        }
    }

    /**
     * Ensure the collaborator currently has all the permissions the folder owner is trying to revoke.
     */
    private function ensureCollaboratorHasPermissions(
        UAC $collaboratorPermissions,
        UAC $permissionsToRevoke
    ): void {
        if (!$collaboratorPermissions->containsAll($permissionsToRevoke)) {
            throw HttpException::notFound([
                'message' => 'UserHasNoSuchPermissions'
            ]);
        }
    }
}
