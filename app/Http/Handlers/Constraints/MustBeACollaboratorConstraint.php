<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MustBeACollaboratorConstraint implements Scope, FolderRequestHandlerInterface
{
    private readonly ?User $authUser;

    /**
     * @param User|null $authUser
     */
    public function __construct($authUser = null)
    {
        $this->authUser = $authUser ?: auth()->user();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (is_null($this->authUser)) {
            return;
        }

        $builder->addSelect(['user_id'])->tap(new UserIsACollaboratorScope($this->authUser->id));
    }

    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser?->id;

        if (is_null($this->authUser) || $folderBelongsToAuthUser) {
            return;
        }

        if (!$folder->userIsACollaborator) {
            throw new FolderNotFoundException();
        }
    }
}
