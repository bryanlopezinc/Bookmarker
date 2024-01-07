<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use App\ValueObjects\UserId;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RevokeFolderCollaboratorPermissionsService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function revokePermissions(int $collaboratorID, int $folderID, UAC $revokePermissions): void
    {
        $authUserId = UserId::fromAuthUser()->value();

        $folder = Folder::query()
            ->select(['id', 'user_id'])
            ->tap(new UserIsACollaboratorScope($collaboratorID, 'userIsACollaborator'))
            ->tap(new UserIsACollaboratorScope($authUserId, 'authUserIsACollaborator'))
            ->find($folderID);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        $collaboratorPermissions = $this->permissions->all($collaboratorID, $folderID);

        $this->ensureIsNotPerformingActionOnSelf($collaboratorID, $authUserId);

        $this->ensureUserHasPermissionToPerformAction($folder);

        $this->ensureUserIsACollaborator($folder);

        $this->ensureCollaboratorHasPermissions($collaboratorPermissions, $revokePermissions);

        $this->permissions->delete($collaboratorID, $folderID, $revokePermissions);
    }

    private function ensureUserHasPermissionToPerformAction(Folder $folder): void
    {
        try {
            FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
        } catch (FolderNotFoundException $e) {
            if (!$folder->authUserIsACollaborator) {
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
            throw HttpException::forbidden(['message' => 'CannotRemoveSelf']);
        }
    }

    private function ensureUserIsACollaborator(Folder $folder): void
    {
        if (!$folder->userIsACollaborator) {
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
        if (!$collaboratorPermissions->containsAll($permissionsToRevoke)) {
            throw HttpException::notFound(['message' => 'UserHasNoSuchPermissions']);
        }
    }
}
