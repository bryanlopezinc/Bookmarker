<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\User;
use App\Notifications\YouHaveBeenBootedOutNotification;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\ValueObjects\UserId;
use Illuminate\Support\Facades\Notification;

final class RemoveCollaboratorService
{
    public function __construct(
        private CollaboratorPermissionsRepository $permissions,
        private CollaboratorRepository $collaboratorRepository
    ) {
    }

    public function revokeUserAccess(int $folderID, int $collaboratorID, bool $banCollaborator): void
    {
        $folder = Folder::query()
            ->tap(new UserIsACollaboratorScope($collaboratorID))
            ->find($folderID, ['id', 'user_id']);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->ensureIsNotRemovingSelf($collaboratorID, UserId::fromAuthUser()->value());

        if (!$folder->userIsACollaborator) {
            throw HttpException::notFound(['message' => 'UserNotACollaborator']);
        }

        $this->collaboratorRepository->delete($folderID, $collaboratorID);

        $this->permissions->delete($collaboratorID, $folderID);

        if ($banCollaborator) {
            BannedCollaborator::query()->create([
                'folder_id' => $folderID,
                'user_id'   => $collaboratorID
            ]);
        }

        Notification::send(
            new User(['id' => $collaboratorID]),
            new YouHaveBeenBootedOutNotification($folder)
        );
    }

    private function ensureIsNotRemovingSelf(int $collaboratorID, int $authUserId): void
    {
        if ($authUserId === $collaboratorID) {
            throw HttpException::forbidden(['message' => 'CannotRemoveSelf']);
        }
    }
}
