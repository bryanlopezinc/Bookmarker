<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\User;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

final class FetchMutedCollaboratorsService
{
    /**
     * @return Paginator<User>
     */
    public function __invoke(int $folderId, PaginationData $pagination, ?string $collaboratorName = null): Paginator
    {
        $folder = Folder::query()->find($folderId, ['id', 'user_id']);

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return $this->collaborators($folder->id, $pagination, $collaboratorName);
    }

    /**
     * @return Paginator<User>
     */
    private function collaborators(int $folderID, PaginationData $pagination, ?string $collaboratorName = null): Paginator
    {
        $mcm = new MutedCollaborator(); //muted collaborator model
        $um = new User(); // user model

        return User::query()
            ->select([$um->getQualifiedKeyName(), 'full_name', 'profile_image_path'])
            ->join($mcm->getTable(), $mcm->qualifyColumn('user_id'), '=', $um->getQualifiedKeyName())
            ->when($collaboratorName, function ($query) use ($collaboratorName) {
                $query->where('full_name', 'like', "{$collaboratorName}%");
            })
            ->where('folder_id', $folderID)
            ->latest($mcm->getQualifiedKeyName())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());
    }
}
