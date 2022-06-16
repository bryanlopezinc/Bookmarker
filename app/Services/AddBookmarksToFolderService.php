<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;

final class AddBookmarksToFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FetchBookmarksRepository $bookmarksRepository,
        private FolderBookmarksRepository $folderBookmarks
    ) {
    }

    public function add(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID, ResourceIDsCollection $makeHidden): void
    {
        (new EnsureAuthorizedUserOwnsResource)($this->repository->findOrFail($folderID, new HttpException));

        $this->validateBookmarks($bookmarkIDs);

        $this->checkFolderForPossibleDuplicates($folderID, $bookmarkIDs);

        $this->folderBookmarks->addBookmarksToFolder($folderID, $bookmarkIDs, $makeHidden);
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

    private function checkFolderForPossibleDuplicates(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->folderBookmarks->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'message' => "Bookmarks already exists"
            ], Response::HTTP_CONFLICT));
        }
    }
}
