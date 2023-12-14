<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\UserCollaboration;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use App\Models\FolderCollaboratorPermission;
use App\Models\Scopes\WhereFolderOwnerExists;
use App\UAC;

final class FetchUserCollaborationsRepository
{
    public function __construct(private PermissionRepository $permissions)
    {
    }

    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $userID, PaginationData $pagination): Paginator
    {
        $query = Folder::onlyAttributes()
            ->tap(new WhereFolderOwnerExists())
            ->addSelect([
                'permissions' => FolderCollaboratorPermission::query()
                    ->selectRaw('JSON_ARRAYAGG(permission_id)')
                    ->whereColumn('folder_id', 'folders.id')
                    ->where('user_id', $userID),
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
