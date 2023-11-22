<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderCollaborator;
use Carbon\Carbon;

final class CollaboratorRepository
{
    public function create(int $folderId, int $collaboratorId, int $inviterId, Carbon $joinedAt = null): void
    {
        FolderCollaborator::create([
            'folder_id'       => $folderId,
            'collaborator_id' => $collaboratorId,
            'invited_by'      => $inviterId,
            'joined_at'       => $joinedAt ?: now()
        ]);
    }

    public function delete(int $folderId, int $collaboratorId): void
    {
        FolderCollaborator::query()
            ->where('folder_id', $folderId)
            ->where('collaborator_id', $collaboratorId)
            ->delete();
    }
}
