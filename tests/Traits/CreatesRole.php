<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use App\Models\User;
use App\Repositories\Folder\PermissionRepository;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;

trait CreatesRole
{
    protected function createRole(string $name = null, Folder $folder = null, Permission|array $permissions = []): FolderRole
    {
        $name = $name ??= fake()->name;
        $folder = $folder ??= FolderFactory::new()->create();
        $permissions = $permissions ? new UAC($permissions) : new UAC([]);

        /** @var FolderRole */
        $newRole = $folder->roles()->save(new FolderRole(['name' => $name]));

        $repository = new PermissionRepository();

        if ($permissions->isNotEmpty()) {
            $permissionIds = $repository->findManyByName($permissions)->pluck('id');

            $newRole->permissions()->createMany($permissionIds->map(fn (int $id) => ['permission_id' => $id]));
        }

        return $newRole->load('permissions');
    }

    protected function attachRoleToUser(User $user = null, FolderRole $role = null): void
    {
        $user = $user ??= UserFactory::new()->create();
        $role = $role ??= $this->createRole();

        FolderCollaboratorRole::query()->create([
            'collaborator_id' => $user->id,
            'role_id'         => $role->id
        ]);
    }
}
