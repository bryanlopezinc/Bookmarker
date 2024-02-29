<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\WhereFolderOwnerExists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class FolderExistConstraint implements Scope, FolderRequestHandlerInterface
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->tap(new WhereFolderOwnerExists());
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        if (!$folder->exists) {
            throw new FolderNotFoundException();
        }
    }
}
