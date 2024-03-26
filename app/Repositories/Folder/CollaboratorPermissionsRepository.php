<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Models\FolderCollaboratorPermission as Model;
use App\UAC;
use App\Models\FolderPermission;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

final class CollaboratorPermissionsRepository
{
    public function all(int $collaboratorId, int $folderID): UAC
    {
        return FolderPermission::query()
            ->select('name')
            ->whereIn(
                'id',
                Model::select('permission_id')
                    ->where('folder_id', $folderID)
                    ->where('user_id', $collaboratorId)
            )
            ->get()
            ->pipe(fn (Collection $permissionNames) => new UAC($permissionNames->all()));
    }

    public function create(int $userID, int $folderID, UAC $folderPermissions): void
    {
        $now = now();

        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderPermission::query()
            ->select([
                new Expression($folderID),
                new Expression($userID),
                new Expression("'{$now}'"),
                'id'
            ])
            ->whereIn('name', $folderPermissions->toArray());

        Model::insertUsing(query: $query, columns: ['folder_id', 'user_id', 'created_at', 'permission_id']);
    }

    public function delete(int $collaboratorID, int $folderID, UAC $permissions = null): void
    {
        $query = Model::query()
            ->where('folder_id', $folderID)
            ->where('user_id', $collaboratorID);

        if ($permissions) {
            $query->whereIn('permission_id', FolderPermission::select('id')->whereIn('name', $permissions->toArray()));
        }

        $query->delete();
    }
}
