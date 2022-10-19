<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Exceptions\HttpException;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class LeaveFolderCollaborationService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderPermissionsRepository $permissionsRepository
    ) {
    }

    public function removeAuthorizedAsCollaborator(ResourceID $folderID): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));

        $this->ensureCollaboratorDoesNotOwnFolder($collaboratorID = UserID::fromAuthUser(), $folder);

        $this->ensureCollaboratorHasAccessToFolder($collaboratorID, $folderID);

        $this->permissionsRepository->removeCollaborator($collaboratorID, $folderID);
    }

    private function ensureCollaboratorHasAccessToFolder(UserID $collaboratorID, ResourceID $folderID): void
    {
        $isNotACollaborator = $this->permissionsRepository->getUserAccessControls($collaboratorID, $folderID)->isEmpty();

        if ($isNotACollaborator) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }

    private function ensureCollaboratorDoesNotOwnFolder(UserID $collaboratorID, Folder $folder): void
    {
        if ($collaboratorID->equals($folder->ownerID)) {
            throw HttpException::forbidden([
                'message' => 'Cannot exit from own folder'
            ]);
        }
    }
}
