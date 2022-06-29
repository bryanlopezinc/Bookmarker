<?php

declare(strict_types=1);

namespace App\Services;

use App\PaginationData;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;
use App\Repositories\FoldersRepository;
use App\Repositories\FolderBookmarksRepository;
use App\Exceptions\FolderNotFoundHttpResponseException;

final class FetchPublicFolderBookmarksService
{
    public function __construct(
        private FolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        $folder = $this->foldersRepository->find($folderID);

        if (!$folder->isPublic) throw new FolderNotFoundHttpResponseException;

        return $this->folderBookmarksRepository->onlyPublicBookmarks($folderID, $pagination);
    }
}
