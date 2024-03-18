<?php

declare(strict_types=1);

namespace App\Http\Handlers\Constraints;

use App\Contracts\FolderRequestHandlerInterface;
use App\Enums\Permission;
use App\Exceptions\PermissionDeniedException;
use App\Models\Folder;
use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository as Repository;
use App\Repositories\Folder\PermissionRepository;
use App\UAC;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PermissionConstraint implements Scope, FolderRequestHandlerInterface
{
    private readonly Repository $repository;
    private readonly User $user;
    private readonly Permission $permission;
    private readonly PermissionRepository $permissionsRepository;

    public function __construct(
        User $user,
        Permission $permission,
        Repository $repository = null,
        PermissionRepository $permissionsRepository = null
    ) {
        $this->user = $user;
        $this->permission = $permission;
        $this->repository = $repository ?: new Repository();
        $this->permissionsRepository = $permissionsRepository ??= new PermissionRepository();
    }

    /**
     * @inheritdoc
     */
    public function apply(Builder $builder, Model $model): void
    {
        $permissionId = $this->permissionsRepository->findByName($this->permission)->id;

        $builder->addSelect([
            'user_id',
            'collaboratorHasRoleWithPermission' => FolderRole::query()
                ->selectRaw('1')
                ->whereColumn('folder_id', 'folders.id')
                ->whereExists(
                    FolderRolePermission::query()
                        ->whereColumn('role_id', 'folders_roles.id')
                        ->where('permission_id', $permissionId)
                )
                ->whereExists(
                    FolderCollaboratorRole::query()
                        ->where('collaborator_id', $this->user->id)
                        ->whereColumn('role_id', 'folders_roles.id')
                )
        ]);
    }

    public function handle(Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $this->user->id;

        if ($folderBelongsToAuthUser) {
            return;
        }

        if ($folder->collaboratorHasRoleWithPermission) {
            return;
        }

        $userPermissions = $this->repository->all($this->user->id, $folder->id);

        if (!$userPermissions->hasAny(new UAC($this->permission))) {
            throw new PermissionDeniedException();
        }
    }
}
