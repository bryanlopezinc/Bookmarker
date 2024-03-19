<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\FolderCollaborator;
use App\Models\FolderCollaboratorPermission as CollaboratorPermission;
use App\Models\User;
use App\PaginationData;
use App\UAC;
use Illuminate\Pagination\Paginator;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;
use App\Models\Scopes\FetchCollaboratorsFilters as Filters;
use App\Repositories\Folder\PermissionRepository;
use Closure;

final class FetchFolderCollaborators
{
    /**
     * @return Paginator<FolderCollaborator>
     */
    public function handle(Request $request, int $folderId): Paginator
    {
        $pagination = PaginationData::fromRequest($request);

        /** @var Paginator */
        $result = User::query()
            ->withCasts(['permissions' => 'json'])
            ->select([
                'users.id',
                'full_name',
                'profile_image_path',
                'permissions' => CollaboratorPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(permission_id)')
                    ->whereColumn('user_id', 'users.id')
                    ->whereColumn('folder_id', 'folders_collaborators.folder_id'),
            ])
            ->join('folders_collaborators', 'folders_collaborators.collaborator_id', '=', 'users.id')
            ->tap(new Filters\FilterByNameScope($request->input('name')))
            ->tap(new Filters\FilterByRoleScope($folderId, $request->input('role')))
            ->tap(new Filters\InviterScope())
            ->tap(new Filters\FilterByPermissionsScope(UAC::fromRequest($request)))
            ->tap(new Filters\FilterByReadOnlyPermissionScope($request->collect('permissions')->containsStrict('readOnly')))
            ->where('folders_collaborators.folder_id', $folderId)
            ->latest('folders_collaborators.id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection($result->map($this->createCollaboratorFn()));
    }

    private function createCollaboratorFn(): Closure
    {
        $permissionsRepository = new PermissionRepository();

        return function (User $model) use ($permissionsRepository) {
            $wasInvitedBy = $model->wasInvitedBy;

            return new FolderCollaborator(
                $model,
                new UAC($permissionsRepository->findManyById($model->permissions ?? [])->all()),
                $wasInvitedBy ? new User($wasInvitedBy) : null
            );
        };
    }
}
