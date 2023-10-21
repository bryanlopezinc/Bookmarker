<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use App\ValueObjects\UserID;

final class GrantPermissionsToCollaboratorService
{
    public function __construct(
        private FetchFolderService $folderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function grant(int $collaboratorId, int $folderId, UAC $permissions): void
    {
        $folder = $this->folderRepository->find($folderId, ['id', 'user_id']);

        $currentPermissions = $this->permissions->getUserAccessControls($collaboratorId, $folderId);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotGrantingPermissionsToSelf($collaboratorId, UserID::fromAuthUser()->value());

        $this->ensureUserIsCurrentlyACollaborator($currentPermissions);

        $this->ensureCollaboratorDoesNotHavePermissions($currentPermissions, $permissions);

        $this->permissions->create($collaboratorId, $folderId, $permissions);
    }

    private function ensureIsNotGrantingPermissionsToSelf(int $collaboratorID, int $authUserId): void
    {
        if ($authUserId === $collaboratorID) {
            throw HttpException::forbidden([
                'message' => 'CannotGrantPermissionsToSelf'
            ]);
        }
    }

    private function ensureUserIsCurrentlyACollaborator(UAC $currentPermissions): void
    {
        if ($currentPermissions->isEmpty()) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }
    }

    private function ensureCollaboratorDoesNotHavePermissions(UAC $currentPermissions, UAC $grant): void
    {
        if ($currentPermissions->containsAny($grant)) {
            throw HttpException::conflict(['message' => 'DuplicatePermissions']);
        }
    }
}
