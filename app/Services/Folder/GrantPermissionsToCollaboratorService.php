<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource as Policy;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use App\ValueObjects\ResourceID as FolderID;
use App\ValueObjects\UserID;

final class GrantPermissionsToCollaboratorService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function grant(UserID $collaboratorID, FolderID $folderID, UAC $permissions): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));
        $collaboratorCurrentPermissions = $this->permissions->getUserAccessControls($collaboratorID, $folderID);

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

    private function ensureUserIsCurrentlyACollaborator(UAC $collaboratorCurrentPermissions): void
    {
        if ($collaboratorCurrentPermissions->isEmpty()) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }

    private function ensureCollaboratorDoesNotHavePermissions(UAC $collaboratorCurrentPermissions, UAC $grantPermissions): void
    {
        if ($collaboratorCurrentPermissions->containsAny($grantPermissions)) {
            throw HttpException::conflict([
                'message' => 'user already has permissions'
            ]);
        }
    }
}
