<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MustBeACollaboratorConstraint implements Scope
{
    public function __construct(private readonly User $user = new User())
    {
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ( ! $this->user->exists) {
            return;
        }

        $builder->addSelect(['user_id'])->tap(new UserIsACollaboratorScope($this->user->id));
    }

    public function __invoke(Folder $folder): void
    {
        if ( ! $this->user->exists) {
            return;
        }

        if ($folder->user_id === $this->user->id) {
            return;
        }

        if ( ! $this->userIsACollaborator($folder)) {
            throw new FolderNotFoundException();
        }
    }

    public function userIsACollaborator(Folder $folder): bool
    {
        return $folder->userIsACollaborator !== null;
    }
}
