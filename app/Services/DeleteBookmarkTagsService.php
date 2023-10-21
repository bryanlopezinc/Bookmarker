<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TagsDetachedEvent;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\HttpException;
use App\Repositories\BookmarkRepository;
use App\Repositories\TagRepository;
use Illuminate\Support\Collection;

final class DeleteBookmarkTagsService
{
    public function __construct(
        private BookmarkRepository $bookmarksRepository,
        private TagRepository $tagsRepository
    ) {
    }

    /**
     * @param array<string> $tags
     */
    public function delete(int $bookmarkId, array $tags): void
    {
        $bookmark = $this->bookmarksRepository->findById($bookmarkId, ['user_id', 'id', 'tags']);

        BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);

        $bookmark->tags
            ->pluck('name')
            ->intersect($tags)
            ->whenEmpty(fn () => throw HttpException::notFound(['message' => 'BookmarkHasNoSuchTags']))
            ->tap(function (Collection $tags) use ($bookmarkId) {
                $this->tagsRepository->detach($tags->all(), $bookmarkId);

                event(new TagsDetachedEvent($tags->all()));
            });
    }
}
