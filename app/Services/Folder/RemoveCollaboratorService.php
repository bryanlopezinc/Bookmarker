<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\BannedCollaborator;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\UserId;

final class RemoveCollaboratorService
{
    public function __construct(
        private FolderPermissionsRepository $permissions,
        private FetchFolderService $folderRepository
    ) {
    }

    public function revokeUserAccess(int $folderID, int $collaboratorID, bool $banCollaborator): void
    {
        $folder = $this->folderRepository->find($folderID, ['id', 'user_id']);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotRemovingSelf($collaboratorID, UserId::fromAuthUser()->value());

        $this->ensureUserIsAnExistingCollaborator($collaboratorID, $folderID);

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
            throw HttpException::forbidden([
                'message' => 'CannotRemoveSelf'
            ]);
        }
    }

    private function ensureUserIsAnExistingCollaborator(int $collaboratorID, int $folderID): void
    {
        $userHasAnyAccessToFolder = $this->permissions->getUserAccessControls($collaboratorID, $folderID)->isNotEmpty();

        if (!$userHasAnyAccessToFolder) {
            throw HttpException::notFound([
                'message' => 'UserNotACollaborator'
            ]);
        }
    }
}
