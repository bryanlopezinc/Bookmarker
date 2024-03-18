<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Response;

final class CannotRemoveAllRolePermissionsConstraint implements FolderRequestHandlerInterface, Scope
{
    private readonly int $roleId;

    public function __construct(int $roleId)
    {
        $this->roleId = $roleId;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'currentPermissionsCount' => FolderRolePermission::query()
                ->selectRaw('COUNT(*)')
                ->where('role_id', $this->roleId)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
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
