<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use Illuminate\Support\Facades\DB;

final class CreateRolePermission implements FolderRequestHandlerInterface
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
        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderPermission::query()
            ->select(DB::raw($this->roleId), 'id')
            ->where('name', $this->permission);

        FolderRolePermission::query()->insertUsing(['role_id', 'permission_id'], $query);
    }
}
