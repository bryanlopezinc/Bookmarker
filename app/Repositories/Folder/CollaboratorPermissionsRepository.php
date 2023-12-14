<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderCollaboratorPermission as Model;
use App\UAC;
use App\Models\FolderPermission;
use Illuminate\Support\Collection;

final class CollaboratorPermissionsRepository
{
    private PermissionRepository $permissions;

    public function __construct(PermissionRepository $permissionRepository = null)
    {
        $this->permissions = $permissionRepository ?: new PermissionRepository();
    }

    public function all(int $collaboratorId, int $folderID): UAC
    {
        $userPermissionsIds = Model::query()
            ->where('user_id', $collaboratorId)
            ->where('folder_id', $folderID)
            ->get(['permission_id'])
            ->pluck('permission_id');

        return new UAC(
            $this->permissions->findManyById($userPermissionsIds)->all()
        );
    }

    public function create(int $userID, int $folderID, UAC $folderPermissions): void
    {
        $createdAt = now();

        $this->permissions->findManyByName($folderPermissions->toArray())
            ->map(fn (FolderPermission $p) => [
                'folder_id'     => $folderID,
                'user_id'       => $userID,
                'permission_id' => $p->id,
                'created_at'    => $createdAt
            ])
            ->tap(function (Collection $records) {
                Model::insert($records->all());
            });
    }

    public function delete(int $collaboratorID, int $folderID, UAC $permissions = null): void
    {
        $query = Model::query()
            ->where('folder_id', $folderID)
            ->where('user_id', $collaboratorID);

        if ($permissions) {
            $query->whereIn('permission_id', $this->permissions->findManyByName($permissions->toArray())->pluck('id'));
        }

        $query->delete();
    }
}
