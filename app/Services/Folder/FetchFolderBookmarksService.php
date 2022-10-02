<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\FolderBookmark;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FetchFolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use App\QueryColumns\FolderAttributes as Attributes;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FetchFolderBookmarksRepository $folderBookmarksRepository,
        private FolderRepositoryInterface $folderRepository
    ) {
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        $folder = $this->folderRepository->find($folderID, Attributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        return $this->folderBookmarksRepository->bookmarks($folderID, $pagination, $userID);
    }
}
