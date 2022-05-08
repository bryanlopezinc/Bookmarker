<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\ValueObjects\ResourceID;
use App\Repositories\DeleteBookmarksRepository;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\UserID;

final class DeleteBookmarkService
{
    public function __construct(
        private BookmarksRepository $bookmarksRepository,
        private DeleteBookmarksRepository $deleteBookmarks
    ) {
    }

    public function delete(ResourceID $bookmarkId): void
    {
        $bookmark = $this->bookmarksRepository->findById($bookmarkId, BookmarkQueryColumns::new()->userId()->id());

        if ($bookmark === false) {
            return;
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        $this->deleteBookmarks->delete($bookmarkId, UserID::fromAuthUser());
    }
}
