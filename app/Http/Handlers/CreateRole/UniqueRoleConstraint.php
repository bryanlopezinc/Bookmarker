<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRole;
use App\UAC;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueRoleConstraint implements Scope
{
    private readonly UAC $permissions;

    public function __construct(UAC $permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'roleWithExactSamePermissions' => FolderRole::query()
                ->select('name')
                ->whereColumn('folder_id', 'folders.id')
                ->whereHas(
                    relation: 'permissions',
                    operator: '=',
                    count: $this->permissions->count(),
                    callback: function (Builder $builder) {
                        $builder->whereIn('name', $this->permissions->toArray());
                    }
                )
        ]);
    }

    public function __invoke(Folder $folder): void
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
