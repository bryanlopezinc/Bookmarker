<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\FolderBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\Repositories\FoldersRepository;
use App\Exceptions\HttpException as HttpException;

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
        (new EnsureAuthorizedUserOwnsResource)($this->repository->find($folderID));

        $this->validateBookmarks($bookmarkIDs);

        $this->checkFolderForPossibleDuplicates($folderID, $bookmarkIDs);

        $this->folderBookmarks->addBookmarksToFolder($folderID, $bookmarkIDs, $makeHidden);
    }

    private function validateBookmarks(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIDs, BookmarkAttributes::new()->userId()->id());

        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            throw HttpException::notFound(['message' => 'The bookmarks does not exists']);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);
    }

    private function checkFolderForPossibleDuplicates(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $exists  = $this->folderBookmarks->getFolderBookmarksFrom($folderID, $bookmarkIDs);

        if ($exists->isNotEmpty()) {
            throw HttpException::conflict(['message' => 'Bookmarks already exists']);
        }
    }
}
