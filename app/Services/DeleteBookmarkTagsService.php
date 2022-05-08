<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\TagsCollection;
use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\ValueObjects\ResourceId;
use App\Repositories\BookmarksRepository;
use App\Repositories\TagsRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteBookmarkTagsService
{
    public function __construct(
        private BookmarksRepository $bookmarksRepository,
        private TagsRepository $tagsRepository
    ) {
    }

    public function delete(ResourceId $bookmarkId, TagsCollection $tagsCollection): void
    {
        $bookmark = $this->bookmarksRepository->findById($bookmarkId, BookmarkQueryColumns::new()->id()->userId());

        if ($bookmark === false) {
            throw new NotFoundHttpException();
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        $this->tagsRepository->detach($tagsCollection, $bookmarkId);
    }
}
