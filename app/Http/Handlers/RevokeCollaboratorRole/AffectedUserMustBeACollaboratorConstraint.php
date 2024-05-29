<?php

declare(strict_types=1);

namespace App\Http\Handlers\RevokeCollaboratorRole;

use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class AffectedUserMustBeACollaboratorConstraint implements Scope
{
    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->tap(new UserIsACollaboratorScope('collaboratorId', 'affectedUserIsACollaborator'));
    }

    public function __invoke(Folder $result): void
    {
        if ( ! $result->affectedUserIsACollaborator) {
            throw HttpException::notFound([
                'message' => 'UserNotACollaborator',
                'info'    => ''
            ]);
        }
    }
}
