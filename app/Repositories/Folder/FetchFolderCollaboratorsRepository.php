<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\FolderCollaborator;
use App\UAC;
use App\Models\FolderPermission;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class FetchFolderCollaboratorsRepository
{
    /**
     * @return Paginator<FolderCollaborator>
     */
    public function collaborators(ResourceID $folderID, PaginationData $pagination, UAC $filter = null): Paginator
    {
        $query = User::select(['users.id', 'firstname', 'lastname', 'folders_access.user_id', new Expression('COUNT(*) AS total')])
            ->join('folders_access', 'folders_access.user_id', '=', 'users.id')
            ->where('folders_access.folder_id', $folderID->value())
            ->groupBy('folders_access.user_id');

        if ($filter === null) {
            return $this->paginate($query, $folderID, $pagination);
        }

        $query->when(!$filter->hasAllPermissions(), function ($query) use ($filter) {
            $query->whereIn('folders_access.permission_id', FolderPermission::select('id')->whereIn('name', $filter->permissions));
            $query->having('total', '=', $filter->count());
        });

        $query->when($filter->hasAllPermissions(), function ($query) {
            $query->havingRaw('total = (SELECT COUNT(*) FROM folders_permissions WHERE name NOT IN(?))', [FolderPermission::VIEW_BOOKMARKS]);
        });

        return $this->paginate($query, $folderID, $pagination);
    }

    private function paginate(Builder $query, ResourceID $folderID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $collaborators = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        $collaboratorsPermissions = $this->getCollaboratorsPermissions($collaborators->getCollection(), $folderID);

        return $collaborators->setCollection(
            $collaborators->map(
                $this->createCollaboratorFn($collaboratorsPermissions)
            )
        );
    }

    private function getCollaboratorsPermissions(Collection $collaborators, ResourceID $folderID): Collection
    {
        return FolderPermission::select('name', 'user_id')
            ->join('folders_access', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->where('folders_access.folder_id', $folderID->value())
            ->whereIn('user_id', $collaborators->pluck('user_id'))
            ->get()
            ->map(fn (FolderPermission $model) => $model->toArray());
    }

    private function createCollaboratorFn(Collection $collaboratorsPermissions): Closure
    {
        return function (User $collaborator) use ($collaboratorsPermissions) {
            return new FolderCollaborator(
                UserBuilder::fromModel($collaborator)->build(),
                new UAC($collaboratorsPermissions->where('user_id', $collaborator->id)->pluck('name')->all())
            );
        };
    }
}
