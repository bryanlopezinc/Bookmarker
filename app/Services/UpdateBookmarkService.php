<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookmarkNotFoundException;
use Illuminate\Http\Response;
use App\Http\Requests\CreateOrUpdateBookmarkRequest as Request;
use App\Repositories\BookmarkRepository;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Tag;
use App\Repositories\TagRepository;

final class UpdateBookmarkService
{
    public function __construct(
        private BookmarkRepository $bookmarksRepository,
        private TagRepository $tagRepository
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $bookmark = $this->bookmarksRepository->findById(
            $request->integer('id'),
            ['user_id', 'tags', 'url', 'id']
        );

        BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);

        $this->ensureMaxBookmarkTagsIsNotExceeded($request, $bookmark);

        $this->ensureTagsWillBeUnique($request, $bookmark);

        $this->performUpdate($request, $bookmark);

        dispatch(new CheckBookmarksHealth([$bookmark]));
    }

    private function ensureTagsWillBeUnique(Request $request, Bookmark $bookmark): void
    {
        $tags = $request->collect('tags');

        if ($tags->isEmpty()) {
            return;
        }

        $hasDuplicates = $bookmark->tags
            ->toBase()
            ->map(fn (Tag $tag) => $tag->name)
            ->intersect($tags)
            ->isNotEmpty();

        if ($hasDuplicates) {
            throw HttpException::conflict(['message' => 'DuplicateTags']);
        }
    }

    private function ensureMaxBookmarkTagsIsNotExceeded(Request $request, Bookmark $bookmark): void
    {
        if ($bookmark->tags->count() + $request->collect('tags')->count() > setting('MAX_BOOKMARK_TAGS')) {
            throw new HttpException(['message' => 'MaxBookmarkTagsLengthExceeded'], Response::HTTP_BAD_REQUEST);
        }
    }

    private function performUpdate(Request $request, Bookmark $bookmark): void
    {
        $request->whenHas('title', function (string $value) use ($bookmark) {
            $bookmark->title = $value;
            $bookmark->has_custom_title = true;
        });

        $request->whenHas('description', function (string $value) use ($bookmark) {
            $bookmark->description = $value;
            $bookmark->description_set_by_user = true;
        });

        $request->whenHas('tags', function (array $tags) use ($bookmark) {
            $this->tagRepository->attach($tags, $bookmark);
        });

        $bookmark->save();
    }
}
