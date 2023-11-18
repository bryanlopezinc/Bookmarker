<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderBookmark;
use App\Enums\FolderBookmarkVisibility;
use App\Enums\FolderVisibility;
use App\Exceptions\FolderNotFoundException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Folder;
use App\Models\FolderBookmark as FolderBookmarkModel;
use App\Models\MutedCollaborator;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use App\Repositories\Folder\FolderPermissionsRepository;
use Illuminate\Support\Collection;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FetchFolderService $folderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(int $folderId, PaginationData $pagination, ?int $authUserId): Paginator
    {
        $folder = $this->folderRepository->find($folderId, ['id', 'user_id', 'visibility']);

        $fetchOnlyPublicBookmarks = (!$authUserId || $folder->user_id !== $authUserId);

        $this->ensureUserCanViewFolderBookmarks($authUserId, $folder);

        $folderBookmarks = $this->getBookmarks(
            $folderId,
            $fetchOnlyPublicBookmarks,
            $authUserId,
            $pagination
        );

        $folderBookmarks
            ->getCollection()
            ->map(fn (FolderBookmark $folderBookmark) => $folderBookmark->bookmark)
            ->tap(fn (Collection $bookmarks) => dispatch(new CheckBookmarksHealth($bookmarks)));

        return $folderBookmarks;
    }

    private function ensureUserCanViewFolderBookmarks(?int $authUserId, Folder $folder): void
    {
        if ($authUserId) {
            try {
                FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);
            } catch (FolderNotFoundException $e) {
                if ($this->permissions->getUserAccessControls($authUserId, $folder->id)->isEmpty()) {
                    throw $e;
                }

                return;
            }
        }

        if (FolderVisibility::from($folder->visibility)->isPrivate()) {
            throw new FolderNotFoundException();
        }
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    private function getBookmarks(
        int $folderId,
        bool $onlyPublic,
        ?int $authUserId,
        PaginationData $pagination
    ): Paginator {
        $model = new Bookmark();
        $fbm = new FolderBookmarkModel(); // FolderBookmarkModel

        /** @var Paginator */
        $result = Bookmark::WithQueryOptions()
            ->join($fbm->getTable(), $fbm->qualifyColumn('bookmark_id'), '=', $model->getQualifiedKeyName())
            ->when($onlyPublic, fn ($query) => $query->where('visibility', FolderBookmarkVisibility::PUBLIC->value))
            ->when(!$onlyPublic, fn ($query) => $query->addSelect(['visibility']))
            ->when($authUserId, function ($query) use ($model, $authUserId) {
                $query->addSelect([
                    'isUserFavorite' => Favorite::query()
                        ->select('id')
                        ->where('user_id', $authUserId)
                        ->whereRaw("bookmark_id = {$model->qualifyColumn('id')}")
                ]);
            })
            ->where('folder_id', $folderId)
            ->whereNotExists(function (&$query) use ($model, $folderId) {
                $query = MutedCollaborator::query()
                    ->select('id')
                    ->whereRaw("user_id = {$model->qualifyColumn('user_id')}")
                    ->where('folder_id', $folderId)
                    ->getQuery();
            })
            ->latest($fbm->getQualifiedKeyName())
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map($this->buildFolderBookmarkObject($onlyPublic))
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
