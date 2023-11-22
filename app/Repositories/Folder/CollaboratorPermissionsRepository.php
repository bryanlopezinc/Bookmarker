<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderCollaboratorPermission as Model;
use App\UAC;
use App\Models\FolderPermission;
use Illuminate\Support\Collection;

final class CollaboratorPermissionsRepository
{
    public function all(int $userID, int $folderID): UAC
    {
        $permissionIdsQuery = Model::query()
            ->select('permission_id')
            ->where('user_id', $userID)
            ->where('folder_id', $folderID);

        return FolderPermission::query()
            ->select('name')
            ->whereIn('id', $permissionIdsQuery)
            ->get()
            ->pluck('name')
            ->pipe(fn (Collection $permissionNames) => new UAC($permissionNames->all()));
    }

    public function create(int $userID, int $folderID, UAC $folderPermissions): void
    {
        $createdAt = now();

        FolderPermission::select('id')
            ->whereIn('name', $folderPermissions->toArray())
            ->get()
            ->pluck('id')
            ->map(fn (int $permissionID) => [
                'folder_id'     => $folderID,
                'user_id'       => $userID,
                'permission_id' => $permissionID,
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
            $subQuery = FolderPermission::select('id')->whereIn('name', $permissions->toArray());

            $query->whereIn('permission_id', $subQuery);
        }

        $query->delete();
    }
}
