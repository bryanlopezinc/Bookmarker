<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UniqueRoleNameConstraint implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly string $role)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'roleNameExists' => FolderRole::query()
                ->selectRaw('id')
                ->whereColumn('folder_id', 'folders.id')
                ->where('name', $this->role)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ($folder->roleNameExists) {
            throw HttpException::conflict(['message' => 'DuplicateRoleName']);
        }
    }
}
