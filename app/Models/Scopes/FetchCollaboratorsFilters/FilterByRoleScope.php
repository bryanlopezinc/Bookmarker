<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use App\Models\FolderCollaboratorRole;
use App\Models\FolderRole;
use Illuminate\Database\Eloquent\Builder;

final class FilterByRoleScope
{
    public function __construct(private readonly int $folderId, private readonly ?string $role)
    {
    }

    public function __invoke(Builder $builder): void
    {
        if ($this->role === null) {
            return;
        }

        $query = FolderRole::query()
            ->where('name', $this->role)
            ->where('folder_id', $this->folderId)
            ->whereExists(
                FolderCollaboratorRole::query()
                    ->whereColumn('collaborator_id', 'folders_collaborators.collaborator_id')
                    ->whereColumn('role_id', 'folders_roles.id')
            );

        $builder->whereExists($query);
    }
}
