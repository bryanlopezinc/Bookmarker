<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;

final class UnMuteCollaboratorService
{
    public function __invoke(FolderPublicId $folderId, UserPublicId $collaboratorId): void
    {
        $folder = Folder::query()
            ->select(['user_id'])
            ->addSelect([
                'muteRecordId' => MutedCollaborator::query()
                    ->select('id')
                    ->whereColumn('folder_id', 'folders.id')
                    ->where('user_id', User::select('id')->tap(new WherePublicIdScope($collaboratorId)))
            ])
            ->tap(new WhereFolderOwnerExists())
            ->tap(new WherePublicIdScope($folderId))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        if ($folder->muteRecordId === null) {
            throw new UserNotFoundException();
        }

        MutedCollaborator::destroy($folder->muteRecordId);
    }
}
