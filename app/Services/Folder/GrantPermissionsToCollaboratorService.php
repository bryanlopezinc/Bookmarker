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

final class GrantPermissionsToCollaboratorService
{
    public function __construct(private CollaboratorPermissionsRepository $permissions)
    {
    }

    public function grant(UserPublicId $collaboratorId, FolderPublicId $folderId, UAC $permissions, int $authUserId): void
    {
        $folder = Folder::query()
            ->select([
                'id',
                'user_id',
                'collaboratorId' => User::select('id')->tap(new WherePublicIdScope($collaboratorId))
            ])
            ->tap(new UserIsACollaboratorScope($collaboratorId))
            ->tap(new WherePublicIdScope($folderId))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotGrantingPermissionsToSelf($folder->collaboratorId, $authUserId);

        $this->ensureUserIsACollaborator($folder);

        $currentPermissions = $this->permissions->all($folder->collaboratorId, $folder->id);

        $this->ensureCollaboratorDoesNotHavePermissions($currentPermissions, $permissions);

        $this->permissions->create($folder->collaboratorId, $folder->id, $permissions);
    }

    private function ensureIsNotGrantingPermissionsToSelf(?int $collaboratorID, int $authUserId): void
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
