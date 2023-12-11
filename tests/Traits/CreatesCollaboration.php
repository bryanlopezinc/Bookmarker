<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Enums\Permission;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Repositories\Folder\CollaboratorRepository;
use App\UAC;

trait CreatesCollaboration
{
    protected function CreateCollaborationRecord(
        User $collaborator,
        Folder $folder,
        Permission|array $permissions = [],
        int $inviter = null
    ): void {
        $permissions = $permissions ? new UAC($permissions) : new UAC([]);

        $repository = new CollaboratorPermissionsRepository;

        if ($permissions->isNotEmpty()) {
            $repository->create($collaborator->id, $folder->id, $permissions);
        }

        (new CollaboratorRepository)->create($folder->id, $collaborator->id, $inviter ?: $folder->user_id);
    }
}
