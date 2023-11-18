<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserId;

final class RemoveCollaboratorService
{
    public function __construct(private FolderPermissionsRepository $permissions)
    {
    }

    public function revokeUserAccess(int $folderID, int $collaboratorID, bool $banCollaborator): void
    {
        $folder = Folder::query()
            ->addSelect([
                'collaborator' => FolderCollaboratorPermission::select('id')
                    ->where('folder_id', $folderID)
                    ->where('user_id', $collaboratorID)
                    ->limit(1)
            ])
            ->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotRemovingSelf($collaboratorID, UserId::fromAuthUser()->value());

        if (!$folder->collaborator) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }

        $this->permissions->removeCollaborator($collaboratorID, $folderID);

        if ($banCollaborator) {
            BannedCollaborator::query()->create([
                'folder_id' => $folderID,
                'user_id'   => $collaboratorID
            ]);
        }
    }

    private function ensureIsNotRemovingSelf(int $collaboratorID, int $authUserId): void
    {
        if ($authUserId === $collaboratorID) {
            throw HttpException::forbidden(['message' => 'CannotRemoveSelf']);
        }
    }
}
