<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Response;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\UpdateBookmarkRepository as Repository;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;

final class UpdateBookmarkService
{
    public function __construct(private Repository $repository, private FetchBookmarksRepository $bookmarksRepository)
    {
    }

    public function fromRequest(UpdateBookmarkRequest $request): void
    {
        $newAttributes = UpdateBookmarkDataBuilder::fromRequest($request)->build();

        $bookmark = $this->bookmarksRepository->findById($newAttributes->id, BookmarkAttributes::only('userId,tags'));

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        if ($bookmark->tags->count() + $newAttributes->tags->count() > setting('MAX_BOOKMARKS_TAGS')) {
            throw new HttpException(['message' => 'Cannot add more tags to bookmark'], Response::HTTP_BAD_REQUEST);
        }

        if ($bookmark->tags->contains($newAttributes->tags)) {
            throw HttpException::conflict(['message' => 'Duplicate tags']);
        }

        $this->repository->update($newAttributes);
    }
}
