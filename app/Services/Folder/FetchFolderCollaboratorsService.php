<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderCollaborator;
use App\Exceptions\FolderNotFoundException;
use App\Models\FolderCollaboratorPermission as CollaboratorPermission;
use App\Models\FolderPermission;
use App\Models\User;
use App\PaginationData;
use App\UAC;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;
use App\Models\Folder;

final class FetchFolderCollaboratorsService
{
    /**
     * @return Paginator<FolderCollaborator>
     */
    public function fromRequest(Request $request): Paginator
    {
        $folder = Folder::query()->find($request->integer('folder_id'), ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return $this->collaborators(
            $folder->id,
            PaginationData::fromRequest($request),
            $request->getPermissions(),
            $request->validated('name')
        );
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    private function collaborators(
        int $folderID,
        PaginationData $pagination,
        UAC $permissions = null,
        ?string $collaboratorName = null
    ): Paginator {
        $collaboratorPermissionsQuery = CollaboratorPermission::query()
            ->whereColumn('user_id', 'users.id')
            ->whereColumn('folder_id', 'folders_collaborators.folder_id');

        $query = User::query()
            ->select(['users.id', 'full_name'])
            ->join('folders_collaborators', 'folders_collaborators.collaborator_id', '=', 'users.id')
            ->addSelect([
                'permissions' => FolderPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereExists(function (&$query) use ($collaboratorPermissionsQuery) {
                        $query = $collaboratorPermissionsQuery->getQuery();
                    }),
                'wasInvitedBy' => User::query()
                    ->selectRaw("JSON_OBJECT('id', id, 'full_name', full_name)")
                    ->whereColumn('id', 'folders_collaborators.invited_by')
            ]);

        if ($permissions) {
            $query->whereExists(function (&$query) use ($permissions, $collaboratorPermissionsQuery) {
                $builder = $collaboratorPermissionsQuery
                    ->select('user_id')
                    ->groupBy('user_id');

                if ($permissions->hasAllPermissions()) {
                    $builder->havingRaw("COUNT(*) = {$permissions->count()}");
                }

                if ($permissions->isReadOnly()) {
                    $builder->havingRaw("COUNT(*) = 1");
                }

                if (!$permissions->isReadOnly()) {
                    $permissionsQuery = FolderPermission::select('id')->whereIn('name', $permissions->toArray());

                    $builder->whereIn('permission_id', $permissionsQuery)->havingRaw("COUNT(*) = {$permissions->count()}");
                }

                $query = $builder->getQuery();
            });
        }

        if ($collaboratorName) {
            $query->where('full_name', 'like', "{$collaboratorName}%");
        }

        /** @var Paginator */
        $result = $query->where('folders_collaborators.folder_id', $folderID)
            ->latest('folders_collaborators.id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection($result->map($this->createCollaboratorFn()));
    }

    private function createCollaboratorFn(): \Closure
    {
        return function (User $model) {
            $model->mergeCasts(['permissions' => 'json', 'wasInvitedBy' => 'json']);

            $wasInvitedBy = $model->wasInvitedBy;

            return new FolderCollaborator(
                $model,
                new UAC($model->permissions),
                $wasInvitedBy ? new User($wasInvitedBy) : null
            );
        };
    }
}
