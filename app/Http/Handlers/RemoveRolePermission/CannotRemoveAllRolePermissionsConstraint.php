<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Response;

final class CannotRemoveAllRolePermissionsConstraint implements Scope
{
    private readonly string $roleIdName;

    public function __construct(string $roleIdName = 'roleId')
    {
        $this->roleIdName = $roleIdName;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'currentPermissionsCount' => FolderRolePermission::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('role_id', $this->roleIdName)
        ]);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->currentPermissionsCount === 1) {
            throw new HttpException(
                status: Response::HTTP_BAD_REQUEST,
                data: [
                    'message' => 'CannotRemoveAllRolePermissions',
                ]
            );
        }
    }
}
