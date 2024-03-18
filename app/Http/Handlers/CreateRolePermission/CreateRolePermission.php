<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\FolderRolePermission;
use App\Repositories\Folder\PermissionRepository;

final class CreateRolePermission implements FolderRequestHandlerInterface
{
    private readonly int $roleId;
    private readonly PermissionRepository $permissionsRepository;
    private readonly string $permission;

    public function __construct(int $roleId, string $permission, PermissionRepository $permissionRepository = null)
    {
        $this->roleId = $roleId;
        $this->permission = $permission;
        $this->permissionsRepository = $permissionRepository ??= new PermissionRepository();
    }

    public function handle(Folder $folder): void
    {
        FolderRolePermission::query()->create([
            'role_id'       => $this->roleId,
            'permission_id' => $this->permissionsRepository->findByName($this->permission)->id
        ]);
    }
}
