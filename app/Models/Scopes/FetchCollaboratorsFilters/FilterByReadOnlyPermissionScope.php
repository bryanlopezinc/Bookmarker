<?php

declare(strict_types=1);

namespace App\Models\Scopes\FetchCollaboratorsFilters;

use App\Models\FolderCollaboratorPermission;
use Illuminate\Database\Eloquent\Builder;

final class FilterByReadOnlyPermissionScope
{
    public function __construct(private readonly bool $shouldFilterByCollaboratorsWithReadOnlyPermission)
    {
    }

    public function __invoke(Builder $builder): void
    {
        if ( ! $this->shouldFilterByCollaboratorsWithReadOnlyPermission) {
            return;
        }

        $builder->whereNotExists(FolderCollaboratorPermission::query()
            ->whereColumn('user_id', 'users.id')
            ->whereColumn('folder_id', 'folders_collaborators.folder_id'));
    }
}
