<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\UserCollaboration;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use App\Models\FolderCollaboratorPermission;
use App\Models\User;
use App\UAC;

final class FetchUserFoldersWhereContainsCollaboratorRepository
{
    public function __construct(private PermissionRepository $permissions)
    {
    }

    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $authUserId, int $collaboratorId, PaginationData $pagination): Paginator
    {
        $query = Folder::onlyAttributes()
            ->addSelect([
                'permissions' => FolderCollaboratorPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(permission_id)')
                    ->whereColumn('folder_id', 'folders.id')
                    ->where('user_id', $collaboratorId)
            ])
            ->where('user_id', $authUserId)
            ->whereExists(
                FolderCollaborator::query()
                    ->where('collaborator_id', $collaboratorId)
                    ->whereColumn('folder_id', 'folders.id')
            )
            ->whereExists(User::select('id')->where('id', $collaboratorId));

        /** @var Paginator */
        $collaborations = $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $collaborations->setCollection(
            $collaborations->map(
                $this->createCollaborationFn()
            )
        );
    }

    private function createCollaborationFn(): \Closure
    {
        return function (Folder $folder) {
            $folder->mergeCasts(['permissions' => 'json']);

            return new UserCollaboration(
                $folder,
                new UAC($this->permissions->findManyById($folder->permissions ?? [])->all())
            );
        };
    }
}
