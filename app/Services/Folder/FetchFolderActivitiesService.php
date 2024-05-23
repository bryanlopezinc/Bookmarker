<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\Scopes\UserIsACollaboratorScope;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Pagination\Paginator;

final class FetchFolderActivitiesService
{
    /**
     * @return Paginator<FolderActivity>
     */
    public function __invoke(FolderPublicId $folderId, User $authUser, PaginationData $pagination): Paginator
    {
        /** @var Folder */
        $folder = Folder::query()
            ->select(['user_id', 'id', 'settings', 'visibility'])
            ->tap(new UserIsACollaboratorScope($authUser->id))
            ->tap(new WherePublicIdScope($folderId))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        $this->ensureFolderIsVisibilityToUser($authUser, $folder);

        $this->ensureUserCanViewActivities($authUser, $folder);

        /** @var Paginator */
        return FolderActivity::query()
            ->with(['resources'])
            ->where('folder_id', $folder->id)
            ->latest('id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());
    }

    private function ensureFolderIsVisibilityToUser(User $user, Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $user->id;

        if ($folder->visibility->isPublic() || $folderBelongsToAuthUser) {
            return;
        }

        if ($folder->visibility->isPrivate() || $folder->visibility->isPasswordProtected()) {
            throw new FolderNotFoundException();
        }

        if ($folder->visibility->isVisibleToCollaboratorsOnly() && ! $folder->userIsACollaborator) {
            throw new FolderNotFoundException();
        }
    }

    private function ensureUserCanViewActivities(User $user, Folder $folder): void
    {
        $folderBelongsToAuthUser = $folder->user_id === $user->id;

        $activitiesVisibility = $folder->settings->activitiesVisibility()->value();

        if ($activitiesVisibility->isPublic() || $folderBelongsToAuthUser) {
            return;
        }

        if ($activitiesVisibility->isVisibleToCollaboratorsOnly() && $folder->userIsACollaborator) {
            return;
        }

        throw HttpException::forbidden([
            'message' => 'CannotViewFolderActivities'
        ]);
    }
}
