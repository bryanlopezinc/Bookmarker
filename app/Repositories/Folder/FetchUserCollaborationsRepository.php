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
use App\Models\DeletedUser;
use App\UAC;
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
            ->join('folders_collaborators_permissions', 'folders_collaborators_permissions.folder_id', '=', 'folders.id')
            ->where('folders_collaborators_permissions.user_id', $userID->value())
            ->whereNotIn('folders.user_id', DeletedUser::select('deleted_users.user_id'))
            ->groupBy('folders_collaborators_permissions.folder_id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $permissions = $this->getCollaboratorPermissions($collaborations->pluck('id')->all(), $userID);

        return $collaborations->setCollection(
            $collaborations->map(
                $this->createCollaborationFn($permissions)
            )
        );
    }

    /**
     * @param array<int> $folderIDs
     */
    private function getCollaboratorPermissions(array $folderIDs, UserID $collaborator): Collection
    {
        if (empty($folderIDs)) {
            return collect([]);
        }

        return FolderPermission::select('name', 'folder_id')
            ->join('folders_collaborators_permissions', 'folders_collaborators_permissions.permission_id', '=', 'folders_permissions.id')
            ->where('user_id', $collaborator->value())
            ->whereIn('folder_id', $folderIDs)
            ->get()
            ->map(fn (FolderPermission $model) => $model->toArray());
    }

    private function createCollaborationFn(Collection $collaboratorsPermissions): \Closure
    {
        return function (Folder $collaboration) use ($collaboratorsPermissions) {
            return new UserCollaboration(
                FolderBuilder::fromModel($collaboration)->build(),
                new UAC($collaboratorsPermissions->where('folder_id', $collaboration->id)->pluck('name')->all())
            );
        };
    }
}
