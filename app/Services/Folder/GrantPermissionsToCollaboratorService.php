<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\Exceptions\HttpException;
use App\FolderPermissions as Permissions;
use App\Policies\EnsureAuthorizedUserOwnsResource as Policy;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\ResourceID as FolderID;
use App\ValueObjects\UserID;

final class GrantPermissionsToCollaboratorService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function grant(UserID $collaboratorID, FolderID $folderID, Permissions $permissions): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));
        $collaboratorCurrentPermissions = $this->permissions->getUserPermissionsForFolder($collaboratorID, $folderID);

        (new Policy)($folder);

        $this->ensureIsNotGrantingPermissionsToSelf($collaboratorID);

        $this->ensureUserIsCurrentlyACollaborator($collaboratorCurrentPermissions);

        $this->ensureCollaboratorDoesNotHavePermissions($collaboratorCurrentPermissions, $permissions);

        $this->permissions->create($collaboratorID, $folderID, $permissions);
    }

    private function ensureIsNotGrantingPermissionsToSelf(UserID $collaboratorID): void
    {
        if (UserID::fromAuthUser()->equals($collaboratorID)) {
            throw HttpException::forbidden([
                'message' => 'Cannot grant permissions to self'
            ]);
        }
    }

    private function ensureUserIsCurrentlyACollaborator(Permissions $collaboratorCurrentPermissions): void
    {
        if (!$collaboratorCurrentPermissions->hasAnyPermission()) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }

    private function ensureCollaboratorDoesNotHavePermissions(
        Permissions $collaboratorCurrentPermissions,
        Permissions $grantPermissions
    ): void {
        if ($collaboratorCurrentPermissions->containsAny($grantPermissions)) {
            throw HttpException::conflict([
                'message' => 'user already has permissions'
            ]);
        }
    }
}
