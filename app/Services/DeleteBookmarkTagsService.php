<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TagsDetachedEvent;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\HttpException;
use App\Models\Bookmark;
use App\Models\Scopes\WherePublicIdScope;
use App\Repositories\TagRepository;
use App\ValueObjects\PublicId\BookmarkPublicId;
use Illuminate\Support\Collection;

final class DeleteBookmarkTagsService
{
    public function __construct(private TagRepository $tagsRepository)
    {
    }

    /**
     * @param array<string> $tags
     */
    public function delete(BookmarkPublicId $bookmarkId, array $tags): void
    {
        $bookmark = Bookmark::select(['user_id', 'id'])
            ->with(['tags'])
            ->tap(new WherePublicIdScope($bookmarkId))
            ->firstOrNew();

        if ( ! $bookmark->exists) {
            throw new BookmarkNotFoundException();
        }

        BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);

        $bookmark->tags
            ->pluck('name')
            ->intersect($tags)
            ->whenEmpty(fn () => throw HttpException::notFound(['message' => 'BookmarkHasNoSuchTags']))
            ->tap(function (Collection $tags) use ($bookmark) {
                $this->tagsRepository->detach($tags->all(), $bookmark->id);

                event(new TagsDetachedEvent($tags->all()));
            });
    }
}
