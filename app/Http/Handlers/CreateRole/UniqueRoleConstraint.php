<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Repositories\Folder\PermissionRepository;
use App\UAC;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

final class UniqueRoleConstraint implements FolderRequestHandlerInterface, Scope
{
    private readonly UAC $permissions;
    private readonly PermissionRepository $permissionsRepository;

    public function __construct(UAC $permissions, PermissionRepository $permissionsRepository = null)
    {
        $this->permissions = $permissions;
        $this->permissionsRepository = $permissionsRepository ??= new PermissionRepository();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $permissionIds = $this->permissionsRepository->findManyByName($this->permissions)->pluck('id');

        $builder->addSelect([
            'roleWithExactSamePermissions' => FolderRole::query()
                ->select('name')
                ->whereColumn('folder_id', 'folders.id')
                ->whereExists(
                    FolderRolePermission::query()
                        ->select(['role_id', DB::raw('COUNT(*) as permissions_count')])
                        ->whereColumn('role_id', 'folders_roles.id')
                        ->whereIn('permission_id', $permissionIds)
                        ->groupBy(['role_id'])
                        ->having('permissions_count', $permissionIds->count())
                )
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $roleWithExactSamePermissions = $folder->roleWithExactSamePermissions;

        if ($roleWithExactSamePermissions !== null) {
            throw HttpException::conflict([
                'message' => 'DuplicateRole',
                'info' => "A role with name {$roleWithExactSamePermissions} already contains exact same permissions"
            ]);
        }
    }
}
