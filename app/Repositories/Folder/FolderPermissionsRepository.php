<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\BannedCollaborator;
use App\Models\FolderAccess;
use App\UAC;
use App\Models\FolderPermission;
use App\ValueObjects\ResourceID as FolderID;
use App\ValueObjects\UserID;
use Illuminate\Support\Collection;

final class FolderPermissionsRepository
{
    /**
     * Get the Permissions a user has to a folder.
     */
    public function getUserAccessControls(UserID $userID, FolderID $folderID): UAC
    {
        return FolderAccess::select('folders_permissions.name')
            ->join('folders_permissions', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->where('folder_id', $folderID->value())
            ->where('user_id', $userID->value())
            ->get()
            ->pluck('name')
            ->pipe(fn (Collection $permissionNames) => new UAC($permissionNames->all()));
    }

    public function create(UserID $userID, FolderID $folderID, UAC $folderPermissions): void
    {
        $createdAt = now();

        FolderPermission::select('id')
            ->whereIn('name', $folderPermissions->permissions)
            ->get()
            ->pluck('id')
            ->map(fn (int $permissionID) => [
                'folder_id' => $folderID->value(),
                'user_id' => $userID->value(),
                'permission_id' => $permissionID,
                'created_at' => $createdAt
            ])
            ->tap(function (Collection $records) {
                FolderAccess::insert($records->all());
            });
    }

    public function removeCollaborator(UserID $collaboratorID, FolderID $folderID): void
    {
        FolderAccess::query()
            ->where('folder_id', $folderID->value())
            ->where('user_id', $collaboratorID->value())
            ->delete();
    }

    public function banCollaborator(UserID $collaboratorID, FolderID $folderID): void
    {
        BannedCollaborator::query()->create([
            'folder_id' => $folderID->value(),
            'user_id' => $collaboratorID->value()
        ]);
    }

    public function isBanned(UserID $collaboratorID, FolderID $folderID): bool
    {
        return BannedCollaborator::query()->where([
            'folder_id' => $folderID->value(),
            'user_id' => $collaboratorID->value()
        ])->exists();
    }

    public function revoke(UserID $collaboratorID, FolderID $folderID, UAC $permissions): void
    {
        FolderAccess::query()
            ->where('folder_id', $folderID->value())
            ->where('user_id', $collaboratorID->value())
            ->whereIn('permission_id',  FolderPermission::select('id')->whereIn('name', $permissions->permissions))
            ->delete();
    }
}
