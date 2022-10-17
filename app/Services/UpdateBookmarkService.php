<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\BookmarksCollection;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use Illuminate\Http\Response;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\UpdateBookmarkRepository as Repository;
use App\DataTransferObjects\UpdateBookmarkData;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;

final class UpdateBookmarkService
{
    public function __construct(private Repository $repository, private FetchBookmarksRepository $bookmarksRepository)
    {
    }

    public function fromRequest(UpdateBookmarkRequest $request): void
    {
        $newAttributes = $this->buildUpdateData($request);
        $bookmark = $this->bookmarksRepository->findById($newAttributes->bookmark->id, BookmarkAttributes::only('user_id,tags,url,id'));

        $canAddMoreTagsToBookmark = $bookmark->tags->count() + $newAttributes->bookmark->tags->count() <= setting('MAX_BOOKMARKS_TAGS');

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        if (!$canAddMoreTagsToBookmark) {
            throw new HttpException(['message' => 'Cannot add more tags to bookmark'], Response::HTTP_BAD_REQUEST);
        }

        if ($bookmark->tags->contains($newAttributes->bookmark->tags)) {
            throw HttpException::conflict(['message' => 'Duplicate tags']);
        }

        $this->repository->update($newAttributes);

        dispatch(new CheckBookmarksHealth(new BookmarksCollection([$bookmark])));
    }

    private function buildUpdateData(UpdateBookmarkRequest $request): UpdateBookmarkData
    {
        $bookmark =  BookmarkBuilder::new()
            ->id((int)$request->validated('id'))
            ->tags($request->validated('tags', []))
            ->when($request->has('title'), fn (BookmarkBuilder $b) => $b->title($request->validated('title')))
            ->when($request->has('description'), fn (BookmarkBuilder $b) => $b->description($request->validated('description')))
            ->build();

        return new UpdateBookmarkData($bookmark);
    }
}
