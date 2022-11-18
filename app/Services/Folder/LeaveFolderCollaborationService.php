<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\Exceptions\HttpException;
use App\Notifications\CollaboratorExitNotification;
use App\QueryColumns\FolderAttributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class LeaveFolderCollaborationService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderPermissionsRepository $permissionsRepository
    ) {
    }

    public function leave(ResourceID $folderID): void
    {
        $folder = $this->folderRepository->find($folderID, FolderAttributes::only('id,user_id,settings'));
        $collaboratorPermissions = $this->permissionsRepository->getUserAccessControls($collaboratorID = UserID::fromAuthUser(), $folderID);

        $this->ensureCollaboratorDoesNotOwnFolder($collaboratorID, $folder);

        $this->ensureCollaboratorHasAccessToFolder($collaboratorPermissions);

        $this->permissionsRepository->removeCollaborator($collaboratorID, $folderID);

        $this->notifyFolderOwner($collaboratorID, $folder, $collaboratorPermissions);
    }

    private function ensureCollaboratorHasAccessToFolder(UAC $folderUAC): void
    {
        if ($folderUAC->isEmpty()) {
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

    private function notifyFolderOwner(UserID $collaboratorThatLeft, Folder $folder, UAC $collaboratorPermissions): void
    {
        $folderNotificationSettings = $folder->settings;

        if (
            $folderNotificationSettings->notificationsAreDisabled() ||
            $folderNotificationSettings->collaboratorExitNotificationIsDisabled() ||
            ($collaboratorPermissions->hasOnlyReadPermission() && $folderNotificationSettings->onlyCollaboratorWithWritePermissionNotificationIsEnabled())
        ) {
            return;
        }

        (new \App\Models\User(['id' => $folder->ownerID->value()]))->notify(
            new CollaboratorExitNotification($folder->folderID, $collaboratorThatLeft)
        );
    }
}
