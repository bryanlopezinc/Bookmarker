<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\WhereFolderOwnerExists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class FolderExistConstraint implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $builder->tap(new WhereFolderOwnerExists());
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }
    }
}
