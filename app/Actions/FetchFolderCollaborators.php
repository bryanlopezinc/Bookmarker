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
use App\Models\FolderPermission;
use App\Models\Scopes\FetchCollaboratorsFilters as Filters;
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
                'users.public_id',
                'full_name',
                'profile_image_path',
                'permissions' => FolderPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereIn(
                        'id',
                        CollaboratorPermission::select('permission_id')
                            ->whereColumn('folder_id', 'folders_collaborators.folder_id')
                            ->whereColumn('user_id', 'users.id')
                    ),
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
        return function (User $model) {
            $wasInvitedBy = $model->wasInvitedBy;

            return new FolderCollaborator(
                $model,
                new UAC($model->permissions ?? []),
                $wasInvitedBy ? new User($wasInvitedBy) : null
            );
        };
    }
}
