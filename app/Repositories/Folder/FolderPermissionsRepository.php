<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderCollaboratorPermission;
use App\UAC;
use App\Models\FolderPermission;
use Illuminate\Support\Collection;

final class FolderPermissionsRepository
{
    /**
     * Get the Permissions a user has to a folder.
     */
    public function getUserAccessControls(int $userID, int $folderID): UAC
    {
        return FolderCollaboratorPermission::select('folders_permissions.name')
            ->join('folders_permissions', 'folders_collaborators_permissions.permission_id', '=', 'folders_permissions.id') //phpcs:ignore
            ->where('folder_id', $folderID)
            ->where('user_id', $userID)
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
                FolderCollaboratorPermission::insert($records->all());
            });
    }

    public function removeCollaborator(int $collaboratorID, int $folderID): void
    {
        FolderCollaboratorPermission::query()
            ->where('folder_id', $folderID)
            ->where('user_id', $collaboratorID)
            ->delete();
    }
}
