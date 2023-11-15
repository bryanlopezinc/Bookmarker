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

final class FetchUserCollaborationsRepository
{
    /**
     * @return Paginator<UserCollaboration>
     */
    public function get(int $userID, PaginationData $pagination): Paginator
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
                            ->where('user_id', $userID)
                    )
            ])

            ->whereIn(
                'id',
                FolderCollaboratorPermission::select('folder_id')
                    ->where('user_id', $userID)
                    ->distinct('folder_id')
            )

            ->whereExists(function (&$query) use ($folderModel) {
                $query = User::query()
                    ->select('id')
                    ->whereRaw("id = {$folderModel->qualifyColumn('user_id')}")
                    ->getQuery();
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
