<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\IsMutedUserScope;
use App\Models\Scopes\UserIsCollaboratorScope;

final class MuteCollaboratorService
{
    public function __invoke(int $folderId, int $collaboratorId, int $authUserId): void
    {
        $folder = Folder::onlyAttributes(['user_id'])
            ->tap(new UserIsCollaboratorScope($collaboratorId))
            ->tap(new IsMutedUserScope($collaboratorId))
            ->whereKey($folderId)
            ->first();

        FolderNotFoundException::throwIf(is_null($folder));

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->user_id === $collaboratorId) {
            throw HttpException::forbidden(['message' => 'CannotMuteSelf']);
        }

        if (!$folder->userIsCollaborator) {
            throw new UserNotFoundException();
        }

        if ($folder->collaboratorIsMuted) {
            return;
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
