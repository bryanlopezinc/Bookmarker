<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\Policies\EnsureAuthorizedUserOwnsFolder;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class AddBookmarksToFolderService
{
    public function __construct(private FoldersRepository $repository, private BookmarksRepository $bookmarksRepository)
    {
    }

    public function add(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $this->validateFolder($folderID);

        $this->validateBookmarks($bookmarkIDs);

        $this->checkFolderForPossibleDuplicates($folderID, $bookmarkIDs);

        $this->repository->addBookmarksToFolder($folderID, $bookmarkIDs);
    }

    private function validateFolder(ResourceID $folderID): void
    {
        $folder = $this->repository->findByID($folderID);

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsFolder)($folder);
    }

    private function validateBookmarks(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkQueryColumns::new()->userId()->id());

        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            throw new HttpResponseException(response()->json([
                'message' => "The bookmarks does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsBookmark);
    }

    private function checkFolderForPossibleDuplicates(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->repository->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'message' => "Bookmarks already exists"
            ], Response::HTTP_CONFLICT));
        }
    }
}