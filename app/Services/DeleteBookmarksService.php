<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\DeleteBookmarksRepository;
use App\Repositories\FetchBookmarksRepository;

final class DeleteBookmarksService
{
    public function __construct(
        private FetchBookmarksRepository $bookmarksRepository,
        private DeleteBookmarksRepository $deleteBookmarks
    ) {
    }

    public function delete(ResourceIDsCollection $bookmarkIds): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIds, BookmarkAttributes::only('userId,id'));

        if ($bookmarks->isEmpty()) {
            return;
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        $this->deleteBookmarks->delete($bookmarkIds);
    }
}
