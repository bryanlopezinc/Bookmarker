<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\User;
use App\Notifications\CollaboratorExitNotification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\UAC;
use Illuminate\Support\Facades\Notification;

final class LeaveFolderCollaborationService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissionsRepository,
        private CollaboratorRepository $collaboratorRepository
    ) {
    }

    public function leave(int $folderID): void
    {
        /** @var User */
        $collaborator = auth()->user();

        $folder = Folder::onlyAttributes(['id', 'user_id', 'settings'])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new UserIsACollaboratorScope($collaborator->id))
            ->find($folderID);

        $collaboratorPermissions = $this->permissionsRepository->all($collaborator->id, $folderID);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        $this->ensureCollaboratorDoesNotOwnFolder($collaborator->id, $folder);

        FolderNotFoundException::throwIf( ! $folder->userIsACollaborator);

        $this->collaboratorRepository->delete($folder->id, $collaborator->id);

        $this->permissionsRepository->delete($collaborator->id, $folderID);

        $this->notifyFolderOwner($collaborator, $folder, $collaboratorPermissions);
    }

    private function ensureCollaboratorDoesNotOwnFolder(int $collaboratorID, Folder $folder): void
    {
        if ($collaboratorID === $folder->user_id) {
            throw HttpException::forbidden(['message' => 'CannotExitOwnFolder']);
        }
    }

    private function notifyFolderOwner(User $collaborator, Folder $folder, UAC $collaboratorPermissions): void
    {
        $folderNotificationSettings = $folder->settings;

        if ($folderNotificationSettings->notificationsAreDisabled) {
            return;
        }

        if ($folderNotificationSettings->collaboratorExitNotificationIsDisabled) {
            return;
        }

        if (
            $collaboratorPermissions->isEmpty() &&
            $folderNotificationSettings->collaboratorExitNotificationMode->notifyWhenCollaboratorHasWritePermission()
        ) {
            return;
        }

        Notification::send(
            new User(['id' => $folder->user_id]),
            new CollaboratorExitNotification($folder, $collaborator)
        );
    }
}
