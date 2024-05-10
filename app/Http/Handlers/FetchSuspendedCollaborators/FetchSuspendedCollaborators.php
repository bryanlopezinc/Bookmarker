<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchSuspendedCollaborators;

use App\Models\Folder;
use App\Models\SuspendedCollaborator;
use App\Models\User;
use App\PaginationData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

final class FetchSuspendedCollaborators
{
    public function __construct(private readonly ?string $name, private PaginationData $pagination)
    {
    }

    /**
     * @return Paginator<SuspendedCollaborator>
     */
    public function __invoke(Folder $folder): Paginator
    {
        return SuspendedCollaborator::query()
            ->with(['collaborator:id,public_id,full_name,profile_image_path'])
            ->with(['suspendedByUser:id,public_id,full_name,profile_image_path'])
            ->select(['suspended_at', 'suspended_until', 'duration_in_hours', 'collaborator_id', 'suspended_by'])
            ->when($this->name, function (Builder $query, string $name) {
                $query->whereExists(
                    User::whereColumn('id', 'collaborator_id')->where('full_name', 'like', "{$name}%")
                );
            })
            ->where('folder_id', $folder->id)
            ->whereExists(User::query()->whereColumn('id', 'collaborator_id'))
            ->latest('id')
            ->simplePaginate($this->pagination->perPage(), page: $this->pagination->page());
    }
}
