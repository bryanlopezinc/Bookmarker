<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Models\Folder;
use App\Exceptions\HttpException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CannotSuspendFolderOwnerConstraint implements Scope
{
    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['user_id']);
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->user_id === $folder->collaboratorId) {
            throw HttpException::forbidden(['message' => 'CannotSuspendFolderOwner']);
        }
    }
}
