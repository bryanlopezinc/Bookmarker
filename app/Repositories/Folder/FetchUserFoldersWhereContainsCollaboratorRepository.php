<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\UserCollaboration;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\UAC;
use App\ValueObjects\PublicId\UserPublicId;
use Closure;

final class FetchUserFoldersWhereContainsCollaboratorRepository
{
    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $authUserId, UserPublicId $collaboratorId, PaginationData $pagination): Paginator
    {
        $collaborator = User::select('id')
            ->tap(new WherePublicIdScope($collaboratorId))
            ->firstOrNew();

        if ( ! $collaborator->exists) {
            return new Paginator([], $pagination->perPage());
        }

        $query = Folder::query()
        ->withCount(['bookmarks', 'collaborators'])
            ->withCasts(['permissions' => 'json'])
            ->addSelect([
                'permissions' => FolderPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereIn(
                        'id',
                        FolderCollaboratorPermission::select('permission_id')
                            ->whereColumn('folder_id', 'folders.id')
                            ->where('user_id', $collaborator->id)
                    ),
            ])
            ->where('user_id', $authUserId)
            ->whereExists(
                FolderCollaborator::query()
                    ->where('collaborator_id', $collaborator->id)
                    ->whereColumn('folder_id', 'folders.id')
            );

        /** @var Paginator */
        $collaborations = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $collaborations->setCollection(
            $collaborations->map(
                $this->createCollaborationFn()
            )
        );
    }

    private function createCollaborationFn(): Closure
    {
        return function (Folder $folder) {
            return new UserCollaboration(
                $folder,
                new UAC($folder->permissions ?? [])
            );
        };
    }
}
