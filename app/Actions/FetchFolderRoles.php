<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\PaginationData;
use App\Repositories\Folder\PermissionRepository;
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
        $permissionsRepository = new PermissionRepository();
        $permissions = $permissions ??= new UAC([]);

        /** @var Paginator */
        $roles = FolderRole::query()
            ->with('permissions')
            ->withCount(['assignees as assigneesCount'])
            ->where('folder_id', $folderId)
            ->when($likeName, fn ($query) => $query->where('name', 'LIKE', "{$likeName}%"))
            ->when($permissions->isNotEmpty(), function ($query) use ($permissions, $permissionsRepository) {
                $whereExists = FolderRolePermission::query()
                    ->select(['role_id', DB::raw('COUNT(*) as permissions_count')])
                    ->whereColumn('role_id', 'folders_roles.id')
                    ->whereIn('permission_id', $permissionsRepository->findManyByName($permissions)->pluck('id'))
                    ->groupBy(['role_id']);

                if ($permissions->count() > 1) {
                    $whereExists->having('permissions_count', '>=', $permissions->count());
                }

                $query->whereExists($whereExists);
            })
            ->latest('id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $roles->setCollection(
            $roles->getCollection()->map(function (FolderRole $role) use ($permissionsRepository) {
                return $this->setPermissionNames($role, $permissionsRepository);
            })
        );

        return $roles;
    }

    private function setPermissionNames(FolderRole $role, PermissionRepository $repository): FolderRole
    {
        $permissions = $repository->findManyById($role->permissions->pluck('permission_id'));

        $role->permissionNames = (new UAC($permissions->all()))->toExternalIdentifiers();

        return $role;
    }
}
