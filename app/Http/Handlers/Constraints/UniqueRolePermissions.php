<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueRolePermissions implements Scope
{
    private readonly string $permission;
    private readonly string $roleIdName;

    public function __construct(string $permission, string $roleIdName = 'roleId')
    {
        $this->permission = $permission;
        $this->roleIdName = $roleIdName;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'roleContainsPermission' => FolderRolePermission::query()
                ->selectRaw('1')
                ->whereColumn('role_id', $this->roleIdName)
                ->whereIn('permission_id', FolderPermission::select('id')->where('name', $this->permission))
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->roleContainsPermission) {
            throw HttpException::conflict([
                'message' => 'PermissionAlreadyAttachedToRole',
                'info' => 'Role already contains permission'
            ]);
        }
    }
}
