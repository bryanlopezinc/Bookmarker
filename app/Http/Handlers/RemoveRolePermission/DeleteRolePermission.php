<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;

final class DeleteRolePermission implements FolderRequestHandlerInterface
{
    private readonly int $roleId;
    private readonly string $permission;

    public function __construct(int $roleId, string $permission)
    {
        $this->roleId = $roleId;
        $this->permission = $permission;
    }

    public function handle(Folder $folder): void
    {
        FolderRolePermission::query()
            ->where('role_id', $this->roleId)
            ->whereIn('permission_id', FolderPermission::select('id')->where('name', $this->permission))
            ->delete();
    }
}
