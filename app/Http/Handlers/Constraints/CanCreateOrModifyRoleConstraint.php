<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\PermissionDeniedException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CanCreateOrModifyRoleConstraint implements Scope
{
    private readonly MustBeACollaboratorConstraint $mustBeACollaboratorConstraint;
    private readonly User $authUser;

    public function __construct(User $authUser, MustBeACollaboratorConstraint $mustBeACollaboratorConstraint = null)
    {
        $this->mustBeACollaboratorConstraint = $mustBeACollaboratorConstraint ??= new MustBeACollaboratorConstraint($authUser);
        $this->authUser = $authUser;
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $this->mustBeACollaboratorConstraint->apply($builder, $model);
    }

    public function __invoke(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser->id;

        $constraint = $this->mustBeACollaboratorConstraint;

        if ($folderBelongsToAuthUser) {
            return;
        }

        $constraint($folder);

        if ($this->mustBeACollaboratorConstraint->userIsACollaborator($folder)) {
            throw new PermissionDeniedException();
        }
    }
}
