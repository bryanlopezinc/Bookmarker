<?php

declare(strict_types=1);

namespace App\Http\Handlers\AssignRole;

use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use Illuminate\Support\Collection;

final class AssignRoleToCollaborator
{
    public function __invoke(Folder $folder): void
    {
        /** @var int */
        $collaboratorId = $folder->collaboratorId;

        $folder->roles->pluck('id')->map(function (int $roleId) use ($collaboratorId) {
            return [
                'role_id'         => $roleId,
                'collaborator_id' => $collaboratorId,
            ];
        })->tap(function (Collection $records) {
            FolderCollaboratorRole::insert($records->all());
        });
    }
}
