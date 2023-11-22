<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use App\ValueObjects\UserId;

final class GrantPermissionsToCollaboratorService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function grant(int $collaboratorId, int $folderId, UAC $permissions): void
    {
        $folder = Folder::query()->find($folderId, ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        $currentPermissions = $this->permissions->all($collaboratorId, $folderId);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotGrantingPermissionsToSelf($collaboratorId, UserId::fromAuthUser()->value());

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
