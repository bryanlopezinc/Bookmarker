<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\WhereFolderOwnerExists;

final class UnMuteCollaboratorService
{
    public function __invoke(int $folderId, int $collaboratorId): void
    {
        $folder = Folder::onlyAttributes(['user_id'])
            ->addSelect([
                'muteRecordId' => MutedCollaborator::select('id')->where([
                    'folder_id' => $folderId,
                    'user_id'   => $collaboratorId,
                ])
            ])
            ->tap(new WhereFolderOwnerExists())
            ->whereKey($folderId)
            ->first();

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->muteRecordId === null) {
            throw new UserNotFoundException();
        }

        MutedCollaborator::destroy($folder->muteRecordId);
    }
}
