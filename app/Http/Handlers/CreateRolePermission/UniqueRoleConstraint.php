<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Repositories\Folder\PermissionRepository;
use Illuminate\Support\Facades\DB;

final class UniqueRoleConstraint implements FolderRequestHandlerInterface
{
    private readonly string $permission;
    private readonly PermissionRepository $permissionsRepository;
    private readonly int $roleId;

    public function __construct(int $roleId, string $permission, PermissionRepository $permissionsRepository = null)
    {
        $this->permission = $permission;
        $this->roleId = $roleId;
        $this->permissionsRepository = $permissionsRepository ??= new PermissionRepository();
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $roleExpectedPermissions = FolderRolePermission::query()
            ->where('role_id', $this->roleId)
            ->get(['permission_id'])
            ->pluck('permission_id')
            ->push($this->permissionsRepository->findByName($this->permission)->id);

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
