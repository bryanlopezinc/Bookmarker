<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use Illuminate\Support\Facades\DB;

final class UniqueRoleConstraint
{
    private readonly string $permission;
    private readonly string $roleIdName;

    public function __construct(string $permission, string $roleIdName = 'roleId')
    {
        $this->permission = $permission;
        $this->roleIdName = $roleIdName;
    }

    public function __invoke(Folder $folder): void
    {
        /** @var \Illuminate\Database\Eloquent\Builder */
        $union = FolderPermission::select('id')->where('name', $this->permission);

        $roleExpectedPermissions = FolderRolePermission::query()
            ->where('role_id', $folder->{$this->roleIdName})
            ->unionAll($union)
            ->get(['permission_id'])
            ->pluck('permission_id');

        $roleWithExactSamePermissions = FolderRole::query()
            ->select('name')
            ->where('folder_id', $folder->id)
            ->whereExists(
                FolderRolePermission::query()
                    ->select(['role_id', DB::raw('COUNT(*) as permissions_count')])
                    ->whereColumn('role_id', 'folders_roles.id')
                    ->whereIn('permission_id', $roleExpectedPermissions)
                    ->groupBy(['role_id'])
                    ->having('permissions_count', $roleExpectedPermissions->count())
            )
            ->first();

        if ($roleWithExactSamePermissions !== null) {
            throw HttpException::conflict([
                'message' => 'DuplicateRole',
                'info' => "A role with name {$roleWithExactSamePermissions->name} already contains exact same permissions"
            ]);
        }
    }
}
