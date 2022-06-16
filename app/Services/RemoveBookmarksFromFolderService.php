<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;

final class RemoveBookmarksFromFolderService
{
    public function __construct(
        private FoldersRepository $repository,
        private FolderBookmarksRepository $folderBookmarks
    ) {
    }

    public function remove(ResourceIDsCollection $bookmarkIDs, ResourceID $folderID): void
    {
        (new EnsureAuthorizedUserOwnsResource)($this->repository->findOrFail($folderID, new HttpException));

        $this->ensureBookmarksExistsInFolder($folderID, $bookmarkIDs);

        $this->folderBookmarks->removeBookmarksFromFolder($folderID, $bookmarkIDs);
    }

    private function ensureBookmarksExistsInFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->folderBookmarks->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->count() !== $bookmarkIDs->count()) {
            throw new HttpResponseException(response()->json([
                'message' => "Bookmarks does not exists in folder"
            ], Response::HTTP_NOT_FOUND));
        }
    }
}
