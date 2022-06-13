<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\BookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\Repositories\FoldersRepository;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class HideFolderBookmarksService
{
    public function __construct(
        private FolderBookmarksRepository $folderBookmarksRepository,
        private FoldersRepository $foldersRepository,
        private BookmarksRepository $bookmarksRepository
    ) {
    }

    public function hide(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        (new EnsureAuthorizedUserOwnsResource)($this->foldersRepository->findOrFail($folderID, new FolderNotFoundHttpResponseException));

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->folderBookmarksRepository->makeHidden($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->folderBookmarksRepository->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->count() !== $bookmarkIDs->count()) {
            throw new HttpResponseException(response()->json([
                'message' => "Bookmarks does not exists in folder"
            ], Response::HTTP_NOT_FOUND));
        }
    }
}
