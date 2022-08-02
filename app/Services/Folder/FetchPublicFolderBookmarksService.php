<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\PaginationData;
use App\ValueObjects\ResourceID;
use Illuminate\Pagination\Paginator;
use App\Repositories\Folder\FolderRepository;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\QueryColumns\FolderAttributes as Attributes;
use App\DataTransferObjects\FolderBookmark;

final class FetchPublicFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FolderRepository $folderRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        $folder = $this->folderRepository->find($folderID, Attributes::only('privacy'));

        if (!$folder->isPublic) throw new FolderNotFoundHttpResponseException;

        return $this->folderBookmarksRepository->onlyPublicBookmarks($folderID, $pagination);
    }
}
