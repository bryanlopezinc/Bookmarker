<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
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

        $builder->whereExists(function (&$query) {
            $builder = FolderCollaboratorPermission::query()
                ->whereColumn('folder_id', 'folders_collaborators.folder_id')
                ->whereColumn('user_id', 'users.id')
                ->select('user_id', 'folder_id')
                ->groupBy('user_id', 'folder_id');

            if ($this->permissions->hasAll()) {
                $builder->havingRaw("COUNT(*) = {$this->permissions->count()}");
            }

            if ( ! $this->permissions->hasAll() && $this->permissions->isNotEmpty()) {
                $builder
                    ->whereIn('permission_id', FolderPermission::select('id')->whereIn('name', $this->permissions->toArray()))
                    ->havingRaw("COUNT(*) >= {$this->permissions->count()}");
            }

            $query = $builder->getQuery();
        });
    }
}
