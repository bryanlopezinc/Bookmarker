<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Bookmark;
use App\PaginationData;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FolderBookmarksRepository;
use App\Repositories\FoldersRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;

final class FetchFolderBookmarksService
{
    public function __construct(
        private FolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function fetch(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        $this->validateFolder($folderID);

        return $this->folderBookmarksRepository->bookmarks($folderID, $pagination, $userID);
    }

    private function validateFolder(ResourceID $folderID): void
    {
        $folder = $this->foldersRepository->findByID($folderID);

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsResource)($folder);
    }
}
