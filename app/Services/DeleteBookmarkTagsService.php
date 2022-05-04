<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\TagsCollection;
use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\ValueObjects\ResourceId;
use App\Repositories\DeleteBookmarkTagsRepository;
use App\Repositories\FindBookmarksRepository as FindBookmarksRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteBookmarkTagsService
{
    public function __construct(
        private FindBookmarksRepository $findBookmarks,
        private DeleteBookmarkTagsRepository $deleteBookmarkTagsRepository
    ) {
    }

    public function delete(ResourceId $bookmarkId, TagsCollection $tagsCollection): void
    {
        $bookmark = $this->findBookmarks->findById($bookmarkId, BookmarkQueryColumns::new()->id()->userId());

        if ($bookmark === false) {
            throw new NotFoundHttpException();
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        $this->deleteBookmarkTagsRepository->delete($bookmarkId, $tagsCollection);
    }
}
