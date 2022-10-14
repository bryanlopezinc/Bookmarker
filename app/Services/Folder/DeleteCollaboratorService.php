<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\User;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\QueryColumns\UserAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\UserRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class DeleteCollaboratorService
{
    public function __construct(
        private UserRepository $userRepository,
        private FolderPermissionsRepository $permissions,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    public function revokeUserAccess(ResourceID $folderID, UserID $collaboratorID): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id'));
        $collaborator = $this->retrieveCollaboratorData($collaboratorID);

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $this->ensureIsNotRemovingSelf($collaborator);

        $this->ensureUserIsACollaborator($collaborator, $folderID);

        $this->permissions->removeCollaborator($collaborator->id, $folderID);
    }

    private function retrieveCollaboratorData(UserID $collaboratorID): User
    {
        $collaborator = $this->userRepository->findByID($collaboratorID, UserAttributes::only('id'));

        if ($collaborator === false) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }

        return $collaborator;
    }

    private function ensureIsNotRemovingSelf(User $collaborator): void
    {
        if (UserID::fromAuthUser()->equals($collaborator->id)) {
            throw HttpException::forbidden([
                'message' => 'Cannot remove self'
            ]);
        }
    }

    private function ensureUserIsACollaborator(User $collaborator, ResourceID $folderID): void
    {
        $isACollaborator = $this->permissions->getUserPermissionsForFolder($collaborator->id, $folderID)->hasAnyPermission();

        if (!$isACollaborator) {
            throw HttpException::notFound([
                'message' => 'User not a collaborator'
            ]);
        }
    }
}
