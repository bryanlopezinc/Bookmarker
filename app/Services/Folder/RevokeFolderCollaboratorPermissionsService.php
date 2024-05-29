<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;

final class RevokeFolderCollaboratorPermissionsService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function revokePermissions(
        UserPublicId $collaboratorID,
        FolderPublicId $folderID,
        UAC $revokePermissions,
        int $authUserId
    ): void {

        $folder = Folder::query()
            ->select([
                'id',
                'user_id',
                'collaboratorId' => User::select('id')->tap(new WherePublicIdScope($collaboratorID))
            ])
            ->tap(new UserIsACollaboratorScope($collaboratorID, 'userIsACollaborator'))
            ->tap(new UserIsACollaboratorScope($authUserId, 'authUserIsACollaborator'))
            ->tap(new WherePublicIdScope($folderID))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        if( ! $folder->collaboratorId) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }

        $collaboratorPermissions = $this->permissions->all($collaboratorID = $folder->collaboratorId, $folder->id);

        $this->ensureIsNotPerformingActionOnSelf($collaboratorID, $authUserId);

        $this->ensureUserHasPermissionToPerformAction($folder);

        $this->ensureUserIsACollaborator($folder);

        $this->ensureCollaboratorHasPermissions($collaboratorPermissions, $revokePermissions);

        $this->permissions->delete($collaboratorID, $folder->id, $revokePermissions);
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            if ( ! $folder->authUserIsACollaborator) {
                throw $e;
            }

            throw HttpException::forbidden(['message' => 'NoRevokePermissionPermission']);
        }
    }

    private function ensureIsNotPerformingActionOnSelf(int $collaboratorID, int $authUserId): void
    {
        if ($authUserId === $collaboratorID) {
            throw HttpException::forbidden(['message' => 'CannotRemoveSelf']);
        }
    }

    private function ensureUserIsACollaborator(Folder $folder): void
    {
        if ( ! $folder->userIsACollaborator) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }
    }

    /**
     * Ensure the collaborator currently has all the permissions the folder owner is trying to revoke.
     */
    private function ensureCollaboratorHasPermissions(
        UAC $collaboratorPermissions,
        UAC $permissionsToRevoke
    ): void {
        if ( ! $collaboratorPermissions->hasAll($permissionsToRevoke)) {
            throw HttpException::notFound(['message' => 'UserHasNoSuchPermissions']);
        }
    }
}
