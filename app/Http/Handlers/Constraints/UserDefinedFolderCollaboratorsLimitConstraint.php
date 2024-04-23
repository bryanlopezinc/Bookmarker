<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class UserDefinedFolderCollaboratorsLimitConstraint implements Scope
{
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect('settings');
    }

    public function __invoke(Folder $folder): void
    {
        $maxCollaboratorsLimitDefinedByFolderOwner = $folder->settings->maxCollaboratorsLimit;

        if ($maxCollaboratorsLimitDefinedByFolderOwner === -1) {
            return;
        }

        if ($folder->collaborators_count >= $maxCollaboratorsLimitDefinedByFolderOwner) {
            throw HttpException::forbidden([
                'message' => 'MaxFolderCollaboratorsLimitReached',
                'info' => 'The Folder has reached its max collaborators limit set by the folder owner.'
            ]);
        }
    }
}
