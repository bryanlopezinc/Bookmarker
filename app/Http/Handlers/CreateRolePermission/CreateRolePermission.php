<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use Illuminate\Support\Facades\DB;

final class CreateRolePermission
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
        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderPermission::query()
            ->select(DB::raw($folder->{$this->roleIdName}), 'id')
            ->where('name', $this->permission);

        FolderRolePermission::query()->insertUsing(['role_id', 'permission_id'], $query);
    }
}
