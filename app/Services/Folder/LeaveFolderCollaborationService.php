<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\ValueObjects\FolderSettings;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsCollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Notifications\CollaboratorExitNotification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\Repositories\NotificationRepository;
use App\UAC;

final class LeaveFolderCollaborationService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissionsRepository,
        private NotificationRepository $notifications,
        private CollaboratorRepository $collaboratorRepository
    ) {
    }

    public function leave(int $folderID): void
    {
        $collaboratorID = auth()->id();

        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new UserIsCollaboratorScope($collaboratorID))
            ->find($folderID);

        $collaboratorPermissions = $this->permissionsRepository->all($collaboratorID, $folderID);

        FolderNotFoundException::throwIf(!$folder);

        $this->ensureCollaboratorDoesNotOwnFolder($collaboratorID, $folder);

        FolderNotFoundException::throwIf(!$folder->userIsCollaborator);

        $this->collaboratorRepository->delete($folder->id, $collaboratorID);

        $this->permissionsRepository->delete($collaboratorID, $folderID);

        $this->notifyFolderOwner($collaboratorID, $folder, $collaboratorPermissions);
    }

    private function ensureCollaboratorDoesNotOwnFolder(int $collaboratorID, Folder $folder): void
    {
        if ($collaboratorID === $folder->user_id) {
            throw HttpException::forbidden(['message' => 'CannotExitOwnFolder']);
        }
    }

    private function notifyFolderOwner(int $collaborator, Folder $folder, UAC $collaboratorPermissions): void
    {
        $folderNotificationSettings = $folder->settings;

        if ($folderNotificationSettings->notificationsAreDisabled()) {
            return;
        }

        if ($folderNotificationSettings->collaboratorExitNotificationIsDisabled()) {
            return;
        }

        if (
            $collaboratorPermissions->isReadOnly() &&
            $folderNotificationSettings->onlyCollaboratorWithWritePermissionNotificationIsEnabled()
        ) {
            return;
        }

        $this->notifications->notify(
            $folder->user_id,
            new CollaboratorExitNotification($folder->id, $collaborator)
        );
    }
}
