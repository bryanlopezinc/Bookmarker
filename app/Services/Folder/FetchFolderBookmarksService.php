<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\BookmarksCollection;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Folder;
use App\DataTransferObjects\FolderBookmark;
use App\Jobs\CheckBookmarksHealth;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FolderRepositoryInterface $folderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        $folder = $this->folderRepository->find($folderID, Attributes::only('id,user_id'));

        $this->ensureUserCanViewFolderBookmarks($userID, $folder);

        $folderBookmarks = $this->folderBookmarksRepository->bookmarks($folderID, $pagination, $userID);

        $folderBookmarks
            ->getCollection()
            ->map(fn (FolderBookmark $folderBookmark) => $folderBookmark->bookmark)
            ->tap(fn (Collection $bookmarks) => dispatch(new CheckBookmarksHealth(new BookmarksCollection($bookmarks))));

        return $folderBookmarks;
    }

    private function ensureUserCanViewFolderBookmarks(UserID $userID, Folder $folder): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource())($folder);
        } catch (HttpException $e) {
            $userHasAnyAccessToFolder = $this->permissions->getUserAccessControls($userID, $folder->folderID)->isNotEmpty();

            if (!$userHasAnyAccessToFolder) {
                throw $e;
            }
        }
    }
}
