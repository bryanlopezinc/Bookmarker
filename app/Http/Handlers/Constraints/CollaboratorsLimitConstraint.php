<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\FolderCollaboratorsLimitExceededException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CollaboratorsLimitConstraint implements Scope, FolderRequestHandlerInterface
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->withCount('collaborators');
    }

    public function handle(Folder $folder): void
    {
        if ($folder->collaborators_count >= setting('MAX_FOLDER_COLLABORATORS_LIMIT')) {
            throw new FolderCollaboratorsLimitExceededException();
        }
    }
}
