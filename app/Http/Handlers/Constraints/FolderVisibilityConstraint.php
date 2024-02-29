<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Models\Folder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class FolderVisibilityConstraint implements Scope, FolderRequestHandlerInterface
{
    public function apply(Builder|EloquentBuilder $builder, Model $model): void
    {
        $builder->addSelect(['visibility']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $visibility = $folder->visibility;

        if ($visibility->isPublic() || $visibility->isVisibleToCollaboratorsOnly()) {
            return;
        }

        throw new HttpResponseException(
            new JsonResponse(
                ['message' => 'FolderIsMarkedAsPrivate', 'info' => 'Folder has been marked as private by owner.'],
                JsonResponse::HTTP_FORBIDDEN
            )
        );
    }
}
