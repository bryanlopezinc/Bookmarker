<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueRolePermissions implements FolderRequestHandlerInterface, Scope
{
    private readonly string $permission;
    private readonly int $roleId;

    public function __construct(string $permission, int $roleId)
    {
        $this->permission = $permission;
        $this->roleId = $roleId;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'roleContainsPermission' => FolderRolePermission::query()
                ->selectRaw('1')
                ->where('role_id', $this->roleId)
                ->whereIn('permission_id', FolderPermission::select('id')->where('name', $this->permission))
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->roleContainsPermission) {
            throw HttpException::conflict([
                'message' => 'PermissionAlreadyAttachedToRole',
                'info' => 'Role already contains permission'
            ]);
        }
    }
}
