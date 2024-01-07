<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\IsMutedCollaboratorScope;
use App\Models\Scopes\UserIsACollaboratorScope;

final class MuteCollaboratorService
{
    public function __invoke(int $folderId, int $collaboratorId, int $authUserId): void
    {
        $folder = Folder::onlyAttributes(['user_id'])
            ->tap(new UserIsACollaboratorScope($collaboratorId))
            ->tap(new IsMutedCollaboratorScope($collaboratorId))
            ->whereKey($folderId)
            ->first();

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->user_id === $collaboratorId) {
            throw HttpException::forbidden(['message' => 'CannotMuteSelf']);
        }

        if (!$folder->userIsACollaborator) {
            throw new UserNotFoundException();
        }

        if ($folder->collaboratorIsMuted) {
            throw HttpException::conflict(['message' => 'CollaboratorAlreadyMuted']);
        }

        $this->mute($folderId, $collaboratorId, $authUserId);
    }

    public function mute(int $folderId, int|array $collaborators, int $mutedBy): void
    {
        $records = array_map(
            array: (array) $collaborators,
            callback: function (int $collaboratorId) use ($folderId, $mutedBy) {
                return [
                    'folder_id' => $folderId,
                    'user_id'   => $collaboratorId,
                    'muted_by'  => $mutedBy
                ];
            }
        );

        MutedCollaborator::insert($records);
    }
}
