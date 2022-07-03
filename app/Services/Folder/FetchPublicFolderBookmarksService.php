<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\PaginationData;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;
use App\Repositories\Folder\FoldersRepository;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\QueryColumns\FolderAttributes as Attributes;

final class FetchPublicFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        $folder = $this->foldersRepository->find($folderID, Attributes::only('privacy'));

        if (!$folder->isPublic) throw new FolderNotFoundHttpResponseException;

        return $this->folderBookmarksRepository->onlyPublicBookmarks($folderID, $pagination);
    }
}
