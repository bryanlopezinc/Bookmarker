<?php

declare(strict_types=1);

namespace App\Http\Handlers\RevokeCollaboratorRole;

use App\Models\Folder;
use App\Models\FolderCollaboratorRole;

final class RevokeRoleCollaboratorRoles
{
    public function __invoke(Folder $result): void
    {
        FolderCollaboratorRole::query()
            ->where('collaborator_id', $result->collaboratorId)
            ->whereIntegerInRaw('role_id', $result->roles->pluck('id')->all())
            ->delete();
    }
}
