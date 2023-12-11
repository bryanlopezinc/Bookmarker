<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RevokeFolderCollaboratorPermissionsService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function revokePermissions(int $collaboratorID, int $folderID, UAC $revokePermissions): void
    {
        $folder = Folder::query()->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        $collaboratorPermissions = $this->permissions->all($collaboratorID, $folderID);

        $this->ensureIsNotPerformingActionOnSelf($collaboratorID, $authUserId = auth()->id());

        $this->ensureUserHasPermissionToPerformAction($folder, $authUserId);

        $this->ensureUserIsACollaborator($collaboratorPermissions);

        $this->ensureCollaboratorHasPermissions($collaboratorPermissions, $revokePermissions);

        $this->permissions->delete($collaboratorID, $folderID, $revokePermissions);
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder, int $authUserId): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            $userIsACollaborator = FolderCollaborator::query()
                ->where('folder_id', $folder->id)
                ->where('collaborator_id', $authUserId)
                ->exists();

            if (!$userIsACollaborator) {
                throw $e;
            }

            throw new HttpResponseException(
                response()->json(['message' => 'NoRevokePermissionPermission'], 403)
            );
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
