<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderAccess;
use App\FolderPermissions;
use App\Models\FolderPermission;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Support\Collection;

final class FolderPermissionsRepository
{
    /**
     * Get the Permissions a user has to a folder.
     */
    public function getUserPermissionsForFolder(UserID $userID, ResourceID $folderID): FolderPermissions
    {
        return FolderAccess::select('folders_permissions.name')
            ->join('folders_permissions', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->where('folder_id', $folderID->toInt())
            ->where('user_id', $userID->toInt())
            ->get()
            ->pluck('name')
            ->pipe(fn (Collection $permissionNames) => new FolderPermissions($permissionNames->all()));
    }

    public function create(UserID $userID, ResourceID $folderID, FolderPermissions $folderPermissions): void
    {
        $createdAt = now();

        FolderPermission::select('id')
            ->whereIn('name', $folderPermissions->permissions)
            ->get()
            ->pluck('id')
            ->map(fn (int $permissionID) => [
                'folder_id' => $folderID->toInt(),
                'user_id' => $userID->toInt(),
                'permission_id' => $permissionID,
                'created_at' => $createdAt
            ])
            ->tap(function (Collection $records) {
                FolderAccess::insert($records->all());
            });
    }

    public function removeCollaborator(UserID $collaboratorID, ResourceID $folderID): void
    {
        FolderAccess::query()
            ->where('folder_id', $folderID->toInt())
            ->where('user_id', $collaboratorID->toInt())
            ->delete();
    }

    public function revoke(UserID $collaboratorID, ResourceID $folderID, FolderPermissions $permissions): void
    {
        FolderAccess::query()
            ->where('folder_id', $folderID->toInt())
            ->where('user_id', $collaboratorID->toInt())
            ->whereIn('permission_id',  FolderPermission::select('id')->whereIn('name', $permissions->permissions))
            ->delete();
    }
}
