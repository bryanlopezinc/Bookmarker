<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Exceptions\HttpException;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\DataTransferObjects\RemoveCollaboratorData as Data;
use App\Models\Scopes\UserIsACollaboratorScope;

final class CollaboratorToBeRemovedMustBeACollaboratorConstraint implements Scope
{
    public function __construct(private readonly Data $data)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->tap(new UserIsACollaboratorScope($this->data->collaboratorId, 'CollaboratorToBeRemovedIsACollaborator'));
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $folder->CollaboratorToBeRemovedIsACollaborator) {
            throw HttpException::notFound([
                'message' => 'UserNotACollaborator',
                'info'    => ''
            ]);
        }
    }
}
