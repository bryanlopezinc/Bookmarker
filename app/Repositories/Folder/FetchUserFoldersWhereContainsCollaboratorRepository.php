<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\UserCollaboration;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use App\Models\FolderCollaboratorPermission;
use App\Models\User;
use App\UAC;
use Illuminate\Support\Facades\DB;

final class FetchUserFoldersWhereContainsCollaboratorRepository
{
    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $authUserId, int $collaboratorId, PaginationData $pagination): Paginator
    {
        $folderModel = new Folder();

        $query = Folder::onlyAttributes()
            ->addSelect([
                'permissions' => FolderPermission::query()
                    ->select(DB::raw("JSON_ARRAYAGG(name)"))
                    ->whereIn(
                        'id',
                        FolderCollaboratorPermission::select('permission_id')
                            ->whereRaw("folder_id = {$folderModel->qualifyColumn('id')}")
                            ->where('user_id', $collaboratorId)
                    )
            ])
            ->whereIn(
                'id',
                FolderCollaboratorPermission::select('folder_id')
                    ->where('user_id', $collaboratorId)
                    ->distinct('folder_id')
            )
            ->where('user_id', $authUserId)
            ->whereExists(function (&$query) use ($collaboratorId) {
                $query = User::select('id')->where('id', $collaboratorId)->getQuery();
            });

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
            return new UserCollaboration(
                $folder,
                new UAC(json_decode($folder->permissions, true, flags: JSON_THROW_ON_ERROR))
            );
        };
    }
}
