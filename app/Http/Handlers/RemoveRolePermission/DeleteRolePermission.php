<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;

final class DeleteRolePermission
{
    private readonly string $roleIdName;
    private readonly string $permission;

    public function __construct(string $permission, string $roleIdName = 'roleId')
    {
        $this->roleIdName = $roleIdName;
        $this->permission = $permission;
    }

    public function __invoke(Folder $folder): void
    {
        FolderRolePermission::query()
            ->where('role_id', $folder->{$this->roleIdName})
            ->whereIn('permission_id', FolderPermission::select('id')->where('name', $this->permission))
            ->delete();
    }
}
