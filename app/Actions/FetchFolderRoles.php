<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FolderPermission;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\PaginationData;
use App\UAC;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

final class FetchFolderRoles
{
    public function handle(
        int $folderId,
        PaginationData $pagination,
        UAC $permissions = null,
        string $likeName = null,
    ): Paginator {
        $permissions = $permissions ??= new UAC([]);

        /** @var Paginator */
        $roles = FolderRole::query()
            ->with('permissions')
            ->withCount(['assignees as assigneesCount'])
            ->where('folder_id', $folderId)
            ->when($likeName, fn ($query) => $query->where('name', 'LIKE', "{$likeName}%"))
            ->when($permissions->isNotEmpty(), function ($query) use ($permissions) {
                $whereExists = FolderRolePermission::query()
                    ->select(['role_id', DB::raw('COUNT(*) as permissions_count')])
                    ->whereColumn('role_id', 'folders_roles.id')
                    ->whereIn('permission_id', FolderPermission::select('id')->whereIn('name', $permissions->toArray()))
                    ->groupBy(['role_id']);

                if ($permissions->count() > 1) {
                    $whereExists->having('permissions_count', '>=', $permissions->count());
                }

                $query->whereExists($whereExists);
            })
            ->latest('id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $roles->setCollection(
            $roles->getCollection()->map(function (FolderRole $role) {
                return $this->setPermissionNames($role);
            })
        );

        return $roles;
    }

    private function setPermissionNames(FolderRole $role): FolderRole
    {
        $role->permissionNames = $role->accessControls()->toExternalIdentifiers();

        return $role;
    }
}
