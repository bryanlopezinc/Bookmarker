<?php

declare(strict_types=1);

namespace App\Services;

use App\PaginationData;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;
use App\Repositories\FoldersRepository;
use App\Repositories\FolderBookmarksRepository;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;

final class FetchSharedFolderBookmarksService
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
        $folder = $this->foldersRepository->findOrFail($folderID, $exception = new HttpException);

        if (!$folder->isPublic) throw $exception;

        return $this->folderBookmarksRepository->onlyPublicBookmarks($folderID, $pagination);
    }
}
