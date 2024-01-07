<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderCollaborator;
use App\Exceptions\FolderNotFoundException;
use App\Models\FolderCollaboratorPermission as CollaboratorPermission;
use App\Models\User;
use App\PaginationData;
use App\UAC;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;
use App\Models\Folder;
use App\Repositories\Folder\PermissionRepository;

final class FetchFolderCollaboratorsService
{
    public function __construct(private PermissionRepository $permissions)
    {
    }

    /**
     * @return Paginator<FolderCollaborator>
     */
    public function fromRequest(Request $request): Paginator
    {
        $folder = Folder::query()->find($request->route('folder_id'), ['id', 'user_id']);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

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
            ->withCasts(['permissions' => 'json', 'wasInvitedBy' => 'json'])
            ->select(['users.id', 'full_name', 'profile_image_path'])
            ->join('folders_collaborators', 'folders_collaborators.collaborator_id', '=', 'users.id')
            ->addSelect([
                'permissions' => CollaboratorPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(permission_id)')
                    ->whereColumn('user_id', 'users.id')
                    ->whereColumn('folder_id', 'folders_collaborators.folder_id'),
                'wasInvitedBy' => User::query()
                    ->selectRaw("JSON_OBJECT('id', id, 'full_name', full_name, 'profile_image_path', profile_image_path)")
                    ->whereColumn('id', 'folders_collaborators.invited_by')
            ]);

        if ($permissions?->isEmpty()) {
            $query->whereNotExists($collaboratorPermissionsQuery);
        }

        if ($permissions?->isNotEmpty()) {
            $query->whereExists(function (&$query) use ($permissions, $collaboratorPermissionsQuery) {
                $builder = $collaboratorPermissionsQuery
                    ->select('user_id', 'folder_id')
                    ->groupBy('user_id', 'folder_id');

                if ($permissions->hasAllPermissions()) {
                    $builder->havingRaw("COUNT(*) = {$permissions->count()}");
                }

                if (!$permissions->hasAllPermissions() && $permissions->isNotEmpty()) {
                    $permissionsQuery = $this->permissions->findManyByName($permissions->toArray())->pluck('id');

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
            $wasInvitedBy = $model->wasInvitedBy;

            return new FolderCollaborator(
                $model,
                new UAC($this->permissions->findManyById($model->permissions ?? [])->all()),
                $wasInvitedBy ? new User($wasInvitedBy) : null
            );
        };
    }
}
