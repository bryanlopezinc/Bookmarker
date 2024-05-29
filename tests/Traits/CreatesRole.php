<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Contracts\IdGeneratorInterface;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderPermission;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Models\User;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

trait CreatesRole
{
    protected function createRole(string $name = null, Folder $folder = null, Permission|array $permissions = []): FolderRole
    {
        /** @var IdGeneratorInterface */
        $idGenerator = app(IdGeneratorInterface::class);

        $name = $name ??= fake()->name;
        $folder = $folder ??= FolderFactory::new()->create();
        $permissions = $permissions ? new UAC($permissions) : new UAC([]);

        /** @var FolderRole */
        $newRole = $folder->roles()->save(new FolderRole(['name' => $name, 'public_id' => $idGenerator->generate()]));

        if ($permissions->isNotEmpty()) {
            $query = FolderPermission::query()
                ->select(DB::raw($newRole->id), 'id')
                ->whereIn('name', $permissions->toArray());

            FolderRolePermission::query()->insertUsing(['role_id', 'permission_id'], $query);
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
