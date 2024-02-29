<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\FolderBookmark;
use App\Enums\FolderBookmarkVisibility;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

final class GetFolderBookmarks
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(?int $authUserId, Folder $folder, PaginationData $pagination): Paginator
    {
        $folderBelongsToAuthUser = $folder->user_id !== $authUserId;

        $fetchOnlyPublicBookmarks = !$authUserId || $folderBelongsToAuthUser;

        $shouldNotIncludeMutedCollaboratorsBookmarks = ($folder->visibility->isPublic() ||
            $folder->visibility->isVisibleToCollaboratorsOnly()) &&
            $authUserId !== null;

        /** @var Paginator */
        $result = Bookmark::WithQueryOptions()
            ->join('folders_bookmarks', 'folders_bookmarks.bookmark_id', '=', 'bookmarks.id')
            ->when($fetchOnlyPublicBookmarks, fn ($query) => $query->where('visibility', FolderBookmarkVisibility::PUBLIC->value))
            ->when(!$fetchOnlyPublicBookmarks, fn ($query) => $query->addSelect(['visibility']))
            ->when($authUserId, function ($query) use ($authUserId) {
                $query->addSelect([
                    'isUserFavorite' => Favorite::query()
                        ->select('id')
                        ->where('user_id', $authUserId)
                        ->whereColumn('bookmark_id', 'bookmarks.id')
                ]);
            })
            ->when($shouldNotIncludeMutedCollaboratorsBookmarks, function ($query) use ($authUserId, $folder) {
                $currentDateTime = now();

                $mutedCollaboratorQuery = MutedCollaborator::query()
                    ->where('folder_id', $folder->id)
                    ->whereColumn('user_id', 'bookmarks.user_id')
                    ->where('muted_by', $authUserId)
                    ->whereRaw("(muted_until IS NULL OR muted_until > '$currentDateTime')");

                $query->whereNotExists($mutedCollaboratorQuery);
            })
            ->where('folder_id', $folder->id)
            ->latest('folders_bookmarks.id')
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map($this->buildFolderBookmarkObject($fetchOnlyPublicBookmarks))
        );

        return $result;
    }

    private function buildFolderBookmarkObject(bool $onlyPublic): \Closure
    {
        return function (Bookmark $model) use ($onlyPublic) {
            $model->isUserFavorite = is_int($model->isUserFavorite);

            if ($onlyPublic) {
                $model->visibility = FolderBookmarkVisibility::PUBLIC->value;
            }

            return new FolderBookmark($model, FolderBookmarkVisibility::from($model->visibility));
        };
    }
}
