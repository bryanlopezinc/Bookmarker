<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Bookmark;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\DeleteBookmarksRepository;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\UserID;

final class DeleteBookmarksService
{
    public function __construct(
        private FetchBookmarksRepository $bookmarksRepository,
        private DeleteBookmarksRepository $deleteBookmarks
    ) {
    }

    public function delete(ResourceIDsCollection $bookmarkIds): void
    {
        $bookmarks = $this->bookmarksRepository->findManyById($bookmarkIds, BookmarkQueryColumns::new()->userId()->id());

        if ($bookmarks->isEmpty()) {
            return;
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        $this->deleteBookmarks->deleteManyFor(UserID::fromAuthUser(), $bookmarkIds);
    }
}
