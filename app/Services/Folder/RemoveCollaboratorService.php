<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class RemoveCollaboratorService
{
    public function __construct(
        private FolderPermissionsRepository $permissions,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    public function revokeUserAccess(ResourceID $folderID, UserID $collaboratorID, bool $banCollaborator): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureIsNotRemovingSelf($collaboratorID);

        $this->ensureUserIsAnExistingCollaborator($collaboratorID, $folderID);

        $this->permissions->removeCollaborator($collaboratorID, $folderID);

        if ($banCollaborator) {
            $this->permissions->banCollaborator($collaboratorID, $folderID);
        }
    }

    private function ensureIsNotRemovingSelf(UserID $collaboratorID): void
    {
        if (UserID::fromAuthUser()->equals($collaboratorID)) {
            throw HttpException::forbidden([
                'message' => 'Cannot remove self'
            ]);
        }
    }

    private function ensureUserIsAnExistingCollaborator(UserID $collaboratorID, ResourceID $folderID): void
    {
        $userHasAnyAccessToFolder = $this->permissions->getUserAccessControls($collaboratorID, $folderID)->isNotEmpty();

        if (!$userHasAnyAccessToFolder) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }
}
