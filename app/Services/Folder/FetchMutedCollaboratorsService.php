<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Pagination\Paginator;

final class FetchMutedCollaboratorsService
{
    /**
     * @return Paginator<User>
     */
    public function __invoke(FolderPublicId $folderId, PaginationData $pagination, ?string $collaboratorName = null): Paginator
    {
        $folder = Folder::query()
            ->select(['id', 'user_id'])
            ->tap(new WherePublicIdScope($folderId))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        return $this->collaborators($folder->id, $pagination, $collaboratorName);
    }

    /**
     * @return Paginator<User>
     */
    private function collaborators(int $folderID, PaginationData $pagination, ?string $collaboratorName = null): Paginator
    {
        $currentDateTime = now();

        return User::query()
            ->select(['users.public_id', 'full_name', 'profile_image_path'])
            ->join('folders_muted_collaborators', 'folders_muted_collaborators.user_id', '=', 'users.id')
            ->when($collaboratorName, function ($query, string $name) {
                $query->where('full_name', 'like', "{$name}%");
            })
            ->where('folder_id', $folderID)
            ->whereRaw("(muted_until IS NULL OR muted_until > '{$currentDateTime}')")
            ->latest('folders_muted_collaborators.id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());
    }
}
