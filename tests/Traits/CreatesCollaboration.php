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
        $permissions = $permissions ? new UAC($permissions) : new UAC(Permission::VIEW_BOOKMARKS);

        $permissions = $permissions->toCollection()
            ->add(Permission::VIEW_BOOKMARKS->value) // add collaborators can view folder bookmarks
            ->unique()
            ->all();

        $repository = new CollaboratorPermissionsRepository;

        $repository->create($collaborator->id, $folder->id, new UAC($permissions));

        (new CollaboratorRepository)->create($folder->id, $collaborator->id, $inviter ?: $folder->user_id);
    }
}
