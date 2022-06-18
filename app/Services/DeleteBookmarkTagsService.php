<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\TagsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\ValueObjects\ResourceID;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\TagsRepository;

final class DeleteBookmarkTagsService
{
    public function __construct(
        private FetchBookmarksRepository $bookmarksRepository,
        private TagsRepository $tagsRepository
    ) {
    }

    public function delete(ResourceID $bookmarkId, TagsCollection $tagsCollection): void
    {
        $bookmark = $this->bookmarksRepository->findById($bookmarkId, BookmarkQueryColumns::new()->id()->userId());

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        $this->tagsRepository->detach($tagsCollection, $bookmarkId);
    }
}
