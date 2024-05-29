<?php

declare(strict_types=1);

namespace App\Http\Handlers\ReInstateSuspendedCollaborator;

use App\Models\Folder;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\PermissionDeniedException;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class FolderMustBelongToUserConstraint implements Scope
{
    public function __construct(private readonly User $authUser)
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['user_id']);

        $builder->tap(new UserIsACollaboratorScope($this->authUser->id, 'authUserIsACollaborator'));
    }

    public function __invoke(Folder $folder): void
    {
        if ($folder->authUserIsACollaborator) {
            throw new PermissionDeniedException();
        }

        if ( ! $folder->wasCreatedBy($this->authUser)) {
            throw new FolderNotFoundException();
        }
    }
}
