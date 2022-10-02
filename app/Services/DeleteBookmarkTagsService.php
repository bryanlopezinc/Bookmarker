<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\TagsCollection;
use App\Events\TagsDetachedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\ValueObjects\ResourceID;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\TagRepository;
use App\ValueObjects\UserID;

final class DeleteBookmarkTagsService
{
    public function __construct(
        private FetchBookmarksRepository $bookmarksRepository,
        private TagRepository $tagsRepository
    ) {
    }

    public function delete(ResourceID $bookmarkId, TagsCollection $tagsCollection): void
    {
        $bookmark = $this->bookmarksRepository->findById($bookmarkId, BookmarkAttributes::only('user_id,id,tags'));

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        if (!$bookmark->tags->contains($tagsCollection)) {
            return;
        }

        $this->tagsRepository->detach($tagsCollection, $bookmarkId);

        event(new TagsDetachedEvent($bookmark->tags, UserID::fromAuthUser()));
    }
}
