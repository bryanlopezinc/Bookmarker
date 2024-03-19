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

final class RoleExistsConstraint implements FolderRequestHandlerInterface, Scope
{
    public function __construct(private readonly int $roleId)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect([
            'roleExists' => FolderRole::query()
                ->selectRaw('1')
                ->whereColumn('folder_id', 'folders.id')
                ->whereKey($this->roleId)
        ]);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if ( ! $folder->roleExists) {
            throw HttpException::notFound(['message' => 'RoleNotFound']);
        }
    }
}
