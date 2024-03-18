<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use App\Models\FolderCollaboratorPermission;
use App\Repositories\Folder\PermissionRepository;
use App\UAC;
use Illuminate\Database\Eloquent\Builder;

final class FilterByPermissionsScope
{
    public function __construct(private readonly UAC $permissions)
    {
    }

    public function __invoke(Builder $builder): void
    {
        if ($this->permissions->isEmpty()) {
            return;
        }

        $permissionsRepository = new PermissionRepository();

        $builder->whereExists(function (&$query) use ($permissionsRepository) {
            $builder = FolderCollaboratorPermission::query()
                ->whereColumn('user_id', 'users.id')
                ->whereColumn('folder_id', 'folders_collaborators.folder_id')
                ->select('user_id', 'folder_id')
                ->groupBy('user_id', 'folder_id');

            if ($this->permissions->hasAllPermissions()) {
                $builder->havingRaw("COUNT(*) = {$this->permissions->count()}");
            }

            if (!$this->permissions->hasAllPermissions() && $this->permissions->isNotEmpty()) {
                $permissionsQuery = $permissionsRepository->findManyByName($this->permissions->toArray())->pluck('id');

                $builder->whereIn('permission_id', $permissionsQuery)->havingRaw("COUNT(*) >= {$this->permissions->count()}");
            }

            $query = $builder->getQuery();
        });
    }
}
