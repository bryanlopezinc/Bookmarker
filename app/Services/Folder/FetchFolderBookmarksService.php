<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\DataTransferObjects\FolderBookmark;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\Repositories\Folder\FoldersRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use App\QueryColumns\FolderAttributes as Attributes;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        $folder = $this->foldersRepository->find($folderID, Attributes::only('id,userId'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        return $this->folderBookmarksRepository->bookmarks($folderID, $pagination, $userID);
    }
}
