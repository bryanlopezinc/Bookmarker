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
    private readonly ?User $user;

    /**
     * @param User|null $user
     */
    public function __construct($user = null)
    {
        $this->user = $user ?: auth()->user();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (is_null($this->user)) {
            return;
        }

        $builder->addSelect(['user_id'])->tap(new UserIsACollaboratorScope($this->user->id));
    }

    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->user?->id;

        if (is_null($this->user) || $folderBelongsToAuthUser) {
            return;
        }

        if (!$this->userIsACollaborator($folder)) {
            throw new FolderNotFoundException();
        }
    }

    public function userIsACollaborator(Folder $folder): bool
    {
        return $folder->userIsACollaborator !== null;
    }
}
