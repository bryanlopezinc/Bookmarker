<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\UserCollaboration;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\PaginationData;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use App\FolderPermissions as Permissions;
use Illuminate\Support\Collection;

final class FetchUserCollaborationsRepository
{
    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $collaborations = Folder::onlyAttributes(new FolderAttributes())
            ->join('folders_access', 'folders_access.folder_id', '=', 'folders.id')
            ->where('folders_access.user_id', $userID->toInt())
            ->groupBy('folders_access.folder_id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $folderCollaboratorPermissions = $this->getCollaboratorPermissions($collaborations->pluck('id')->all(), $userID);

        return $collaborations->setCollection(
            $collaborations->map(
                $this->createCollaborationFn($folderCollaboratorPermissions)
            )
        );
    }

    /**
     * @param array<int> $folderIDs
     */
    private function getCollaboratorPermissions(array $folderIDs, UserID $collaborator): Collection
    {
        return FolderPermission::select('name', 'folder_id')
            ->join('folders_access', 'folders_access.permission_id', '=', 'folders_permissions.id')
            ->where('user_id', $collaborator->toInt())
           ->whereIn('folder_id', $folderIDs)
            ->get()
            ->map(fn (FolderPermission $model) => $model->toArray());
    }

    private function createCollaborationFn(Collection $collaboratorsPermissions): \Closure
    {
        return function (Folder $collaboration) use ($collaboratorsPermissions) {
            return new UserCollaboration(
                FolderBuilder::fromModel($collaboration)->build(),
                new Permissions($collaboratorsPermissions->where('folder_id', $collaboration->id)->pluck('name')->all())
            );
        };
    }
}
