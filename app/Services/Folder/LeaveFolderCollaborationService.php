<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderSettings;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Notifications\CollaboratorExitNotification;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\NotificationRepository;
use App\UAC;
use App\ValueObjects\UserId;

final class LeaveFolderCollaborationService
{
    public function __construct(
        private FolderPermissionsRepository $permissionsRepository,
        private NotificationRepository $notifications
    ) {
    }

    public function leave(int $folderID): void
    {
        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings'])
            ->tap(new WhereFolderOwnerExists())
            ->find($folderID);

        FolderNotFoundException::throwIf(!$folder);

        $collaboratorPermissions = $this->permissionsRepository->getUserAccessControls(
            $collaboratorID = UserId::fromAuthUser()->value(),
            $folderID
        );

        $this->ensureCollaboratorDoesNotOwnFolder($collaboratorID, $folder);

        $this->ensureCollaboratorHasAccessToFolder($collaboratorPermissions);

        $this->permissionsRepository->removeCollaborator($collaboratorID, $folderID);

        $this->notifyFolderOwner($collaboratorID, $folder, $collaboratorPermissions);
    }

    private function ensureCollaboratorHasAccessToFolder(UAC $folderUAC): void
    {
        if ($folderUAC->isEmpty()) {
            throw new FolderNotFoundException();
        }
    }

    private function ensureCollaboratorDoesNotOwnFolder(int $collaboratorID, Folder $folder): void
    {
        if ($collaboratorID === $folder->user_id) {
            throw HttpException::forbidden([
                'message' => 'CannotExitOwnFolder'
            ]);
        }
    }

    private function notifyFolderOwner(int $collaborator, Folder $folder, UAC $collaboratorPermissions): void
    {
        $folderNotificationSettings = FolderSettings::fromQuery($folder->settings);

        if (
            $folderNotificationSettings->notificationsAreDisabled() ||
            $folderNotificationSettings->collaboratorExitNotificationIsDisabled() ||
            ($collaboratorPermissions->hasOnlyReadPermission() &&
                $folderNotificationSettings->onlyCollaboratorWithWritePermissionNotificationIsEnabled())
        ) {
            return;
        }

        $this->notifications->notify(
            $folder->user_id,
            new CollaboratorExitNotification($folder->id, $collaborator)
        );
    }
}
