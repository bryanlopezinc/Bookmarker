<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\FolderCollaborator;
use App\FolderPermissions as Permissions;
use App\Models\FolderPermission;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use Closure;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class FetchFolderCollaboratorsRepository
{
    /**
     * @return Paginator<FolderCollaborator>
     */
    public function collaborators(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $collaborators = User::select(['users.id', 'firstname', 'lastname', 'folders_access.user_id'])
            ->join('folders_access', 'folders_access.user_id', '=', 'users.id')
            ->where('folders_access.folder_id', $folderID->toInt())
            ->groupBy('folders_access.user_id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $collaboratorsPermissions = $this->getCollaboratorsPermissions($collaborators->getCollection());

        return $collaborators->setCollection(
            $collaborators->map(
                $this->createCollaboratorFn($collaboratorsPermissions)
            )
        );
    }

    private function getCollaboratorsPermissions(Collection $collaborators): Collection
    {
        return FolderPermission::select('name', 'user_id')
            ->join('folders_access', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->whereIn('user_id', $collaborators->pluck('user_id'))
            ->get()
            ->map(fn (FolderPermission $model) => $model->toArray());
    }

    private function createCollaboratorFn(Collection $collaboratorsPermissions): Closure
    {
        return function (User $collaborator) use ($collaboratorsPermissions) {
            return new FolderCollaborator(
                UserBuilder::fromModel($collaborator)->build(),
                new Permissions($collaboratorsPermissions->where('user_id', $collaborator->id)->pluck('name')->all())
            );
        };
    }
}
