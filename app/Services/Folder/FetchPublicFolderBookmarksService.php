<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\BookmarksCollection;
use App\Contracts\FolderRepositoryInterface;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\DataTransferObjects\FolderBookmark;
use App\Jobs\CheckBookmarksHealth;
use Illuminate\Support\Collection;

final class FetchPublicFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        $folder = $this->folderRepository->find($folderID, Attributes::only('is_public'));

        if (!$folder->isPublic) {
            throw new FolderNotFoundHttpResponseException();
        }

        $folderBookmarks = $this->folderBookmarksRepository->onlyPublicBookmarks($folderID, $pagination);

        $folderBookmarks
            ->getCollection()
            ->map(fn (FolderBookmark $folderBookmark) => $folderBookmark->bookmark)
            ->tap(fn (Collection $bookmarks) => dispatch(new CheckBookmarksHealth(new BookmarksCollection($bookmarks))));

        return $folderBookmarks;
    }
}
