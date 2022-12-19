<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class RevokeFolderCollaboratorPermissionsService
{
    public function __construct(
        private FolderPermissionsRepository $permissions,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    public function revokePermissions(UserID $collaboratorID, ResourceID $folderID, UAC $revokePermissions): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));
        $collaboratorsCurrentPermissions = $this->permissions->getUserAccessControls($collaboratorID, $folderID);

        (new EnsureAuthorizedUserOwnsResource())($folder);

        $this->ensureIsNotPerformingActionOnSelf($collaboratorID);

        $this->ensureUserIsCurrentlyACollaborator($collaboratorsCurrentPermissions);

        $this->ensureCollaboratorCurrentlyHasPermissions($collaboratorsCurrentPermissions, $revokePermissions);

        $this->permissions->revoke($collaboratorID, $folderID, $revokePermissions);
    }

    private function ensureIsNotPerformingActionOnSelf(UserID $collaboratorID): void
    {
        if (UserID::fromAuthUser()->equals($collaboratorID)) {
            throw HttpException::forbidden([
                'message' => 'Cannot perform action on self'
            ]);
        }
    }

    private function ensureUserIsCurrentlyACollaborator(UAC $collaboratorsCurrentPermissions): void
    {
        if ($collaboratorsCurrentPermissions->isEmpty()) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }

    /**
     * Ensure the collaborator currently has all the permissions the folder owner is trying to revoke.
     */
    private function ensureCollaboratorCurrentlyHasPermissions(
        UAC $collaboratorsCurrentPermissions,
        UAC $permissionsToRevoke
    ): void {
        if (!$collaboratorsCurrentPermissions->containsAll($permissionsToRevoke)) {
            throw HttpException::notFound([
                'message' => 'User does not have such permissions'
            ]);
        }
    }
}
