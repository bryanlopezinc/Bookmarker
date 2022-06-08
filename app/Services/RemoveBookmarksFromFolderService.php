<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class RemoveBookmarksFromFolderService
{
    public function __construct(private FoldersRepository $repository, private BookmarksRepository $bookmarksRepository)
    {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        $this->validateFolder($folderID);

        $this->validateBookmarks($bookmarkIDs);

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->repository->removeBookmarksFromFolder($folderID, $bookmarkIDs);
    }

    private function validateFolder(ResourceID $folderID): void
    {
        $folder = $this->repository->findByID($folderID);

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsResource)($folder);
    }

    private function validateBookmarks(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkQueryColumns::new()->userId()->id());

        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            throw new HttpResponseException(response()->json([
                'message' => "The bookmarks does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->repository->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->count() !== $bookmarkIDs->count()) {
            throw new HttpResponseException(response()->json([
                'message' => "Bookmarks does not exists in folder"
            ], Response::HTTP_NOT_FOUND));
        }
    }
}
