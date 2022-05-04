<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\ValueObjects\ResourceId;
use App\Repositories\DeleteBookmarksRepository;
use App\Repositories\FindBookmarksRepository as FindBookmarksRepository;
use App\ValueObjects\UserId;

final class DeleteBookmarkService
{
    public function __construct(
        private FindBookmarksRepository $findBookmarks,
        private DeleteBookmarksRepository $deleteBookmarks
    ) {
    }

    public function delete(ResourceId $bookmarkId): void
    {
        $bookmark = $this->findBookmarks->findById($bookmarkId, BookmarkQueryColumns::new()->userId()->id());

        if ($bookmark === false) {
            return;
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        $this->deleteBookmarks->delete($bookmarkId, UserId::fromAuthUser());
    }
}
