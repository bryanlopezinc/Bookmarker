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
use App\Models\Scopes\WhereFolderOwnerExists;
use App\UAC;
use Closure;

final class FetchUserCollaborationsRepository
{
    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $userID, PaginationData $pagination): Paginator
    {
        $query = Folder::query()
            ->withCount(['bookmarks', 'collaborators'])
            ->withCasts(['permissions' => 'json'])
            ->tap(new WhereFolderOwnerExists())
            ->addSelect([
                'permissions' => FolderPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(name)')
                    ->whereIn(
                        'id',
                        FolderCollaboratorPermission::select('permission_id')
                            ->whereColumn('folder_id', 'folders.id')
                            ->where('user_id', $userID)
                    ),
            ])
            ->whereExists(
                FolderCollaborator::query()
                    ->where('collaborator_id', $userID)
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
