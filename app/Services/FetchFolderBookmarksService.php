<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\FolderBookmark;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FolderBookmarksRepository;
use App\Repositories\FoldersRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        (new EnsureAuthorizedUserOwnsResource)($this->foldersRepository->findOrFail($folderID, new HttpException));

        return $this->folderBookmarksRepository->bookmarks($folderID, $pagination, $userID);
    }
}
