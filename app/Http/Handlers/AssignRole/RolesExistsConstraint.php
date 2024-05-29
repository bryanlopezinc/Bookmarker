<?php

declare(strict_types=1);

namespace App\Http\Handlers\AssignRole;

use App\Collections\RolesPublicIdsCollection;
use App\Models\Folder;
use App\Exceptions\RoleNotFoundException;

final class RolesExistsConstraint
{
    public function __construct(private readonly RolesPublicIdsCollection $roleIds)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $allRolesExists = $folder->roles->count() === $this->roleIds->values()->count();

        if( ! $allRolesExists) {
            throw new RoleNotFoundException();
        }
    }
}
