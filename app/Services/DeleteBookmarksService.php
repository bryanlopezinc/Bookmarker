<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\DeleteBookmarkRepository;
use App\Repositories\BookmarkRepository;

final class DeleteBookmarksService
{
    public function __construct(
        private BookmarkRepository $bookmarksRepository,
        private DeleteBookmarkRepository $deleteBookmarks
    ) {
    }

    public function delete(ResourceIDsCollection $bookmarkIds): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIds, BookmarkAttributes::only('user_id,id'));

        if ($bookmarks->isEmpty()) {
            return;
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        $this->deleteBookmarks->delete($bookmarkIds);
    }
}
