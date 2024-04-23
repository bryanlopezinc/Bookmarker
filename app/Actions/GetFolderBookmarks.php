<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\FolderBookmark;
use App\Enums\FolderBookmarkVisibility;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Folder;
use App\Models\MutedCollaborator;
use App\Models\Scopes\IsHealthyScope;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use Closure;

final class GetFolderBookmarks
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function handle(?int $authUserId, Folder $folder, PaginationData $pagination): Paginator
    {
        $isLoggedIn = $authUserId !== null;
        $folderBelongsToAuthUser = $folder->user_id !== $authUserId;
        $fetchOnlyPublicBookmarks = ! $isLoggedIn || $folderBelongsToAuthUser;

        $shouldNotIncludeMutedCollaboratorsBookmarks = ($folder->visibility->isPublic() ||
            $folder->visibility->isVisibleToCollaboratorsOnly()) &&
            $isLoggedIn;

        /** @var Paginator */
        $result = Bookmark::query()
            ->select(['bookmarks.id', 'public_id', 'description', 'title', 'url', 'preview_image_url', 'user_id', 'source_id', 'bookmarks.created_at'])
            ->with(['source', 'tags'])
            ->tap(new IsHealthyScope())
            ->join('folders_bookmarks', 'folders_bookmarks.bookmark_id', '=', 'bookmarks.id')
            ->when($fetchOnlyPublicBookmarks, fn ($query) => $query->where('visibility', FolderBookmarkVisibility::PUBLIC->value))
            ->when( ! $fetchOnlyPublicBookmarks, fn ($query) => $query->addSelect(['visibility']))
            ->when($isLoggedIn, function ($query) use ($authUserId) {
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
                    ->whereRaw("(muted_until IS NULL OR muted_until > '{$currentDateTime}')");

                $query->whereNotExists($mutedCollaboratorQuery);
            })
            ->where('folder_id', $folder->id)
            ->latest('folders_bookmarks.id')
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map($this->buildFolderBookmarkObject($fetchOnlyPublicBookmarks, $isLoggedIn))
        );

        return $result;
    }

    private function buildFolderBookmarkObject(bool $onlyPublic, bool $isLoggedIn): Closure
    {
        return function (Bookmark $model) use ($onlyPublic, $isLoggedIn) {
            if ($isLoggedIn) {
                $model->isUserFavorite = is_int($model->isUserFavorite);
            } else {
                $model->isUserFavorite = false;
            }

            if ($onlyPublic) {
                $model->visibility = FolderBookmarkVisibility::PUBLIC->value;
            }

            return new FolderBookmark($model, FolderBookmarkVisibility::from($model->visibility));
        };
    }
}
