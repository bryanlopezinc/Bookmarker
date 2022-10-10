<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderAccess;
use App\FolderPermissions;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Support\Collection;

final class FolderPermissionsRepository
{
    /**
     * Get the Permissions a user has to a folder.
     */
    public function getFolderPermissions(UserID $userID, ResourceID $folderID): FolderPermissions
    {
        return FolderAccess::select('folders_permissions.name')
            ->join('folders_permissions', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->where('folder_id', $folderID->toInt())
            ->where('user_id', $userID->toInt())
            ->get()
            ->pluck('name')
            ->pipe(fn (Collection $permissionNames) => FolderPermissions::fromFolderPermissionsQuery($permissionNames->all()));
    }
}
