<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\MutedCollaborator;

final class MuteCollaboratorService
{
    public function __invoke(int $folderId, int $collaboratorId, int $authUserId): void
    {
        $folder = Folder::onlyAttributes(['user_id'])
            ->addSelect([
                'collaboratorId' => FolderCollaboratorPermission::select('user_id')
                    ->where('folder_id', $folderId)
                    ->where('user_id', $collaboratorId),
            ])
            ->addSelect([
                'collaboratorIsMuted' => MutedCollaborator::select('id')
                    ->where('user_id', $collaboratorId)
                    ->where('folder_id', $folderId)
            ])
            ->whereKey($folderId)
            ->first();

        FolderNotFoundException::throwIf(is_null($folder));

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->user_id === $collaboratorId) {
            throw HttpException::forbidden(['message' => 'CannotMuteSelf']);
        }

        if (!$folder->collaboratorId) {
            throw new UserNotFoundException();
        }

        if ($folder->collaboratorIsMuted) {
            return;
        }

        MutedCollaborator::query()->create([
            'folder_id' => $folderId,
            'user_id'   => $collaboratorId,
            'muted_by'  => $authUserId
        ]);
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
