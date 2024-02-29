<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Enums\Permission;
use App\Exceptions\PermissionDeniedException;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository as Repository;
use App\UAC;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PermissionConstraint implements Scope, FolderRequestHandlerInterface
{
    private readonly Repository $repository;
    private readonly User $authUser;
    private readonly Permission $permission;

    public function __construct(User $authUser, Permission $permission, Repository $repository = null)
    {
        $this->authUser = $authUser;
        $this->permission = $permission;
        $this->repository = $repository ?: new Repository();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->addSelect(['user_id']);
    }

    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->authUser->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        $userPermissions = $this->repository->all($this->authUser->id, $folder->id);

        if (!$userPermissions->hasAny(new UAC($this->permission))) {
            throw new PermissionDeniedException();
        }
    }
}
