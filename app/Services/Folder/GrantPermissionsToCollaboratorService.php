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

final class GrantPermissionsToCollaboratorService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function grant(int $collaboratorId, int $folderId, UAC $permissions): void
    {
        $folder = Folder::query()
            ->select(['id', 'user_id'])
            ->tap(new UserIsACollaboratorScope($collaboratorId))
            ->whereKey($folderId)
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        $currentPermissions = $this->permissions->all($collaboratorId, $folderId);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotGrantingPermissionsToSelf($collaboratorId, UserId::fromAuthUser()->value());

        $this->ensureUserIsACollaborator($folder);

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

    private function ensureUserIsACollaborator(Folder $folder): void
    {
        if ( ! $folder->userIsACollaborator) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }
    }

    private function ensureCollaboratorDoesNotHavePermissions(UAC $currentPermissions, UAC $grant): void
    {
        if ($currentPermissions->hasAny($grant)) {
            throw HttpException::conflict(['message' => 'DuplicatePermissions']);
        }
    }
}
