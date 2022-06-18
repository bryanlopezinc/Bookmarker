<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Response;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Repositories\FetchBookmarksRepository;
use App\Repositories\UpdateBookmarkRepository as Repository;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;
use App\Exceptions\HttpException;
use App\Http\Requests\CreateBookmarkRequest;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;

final class UpdateBookmarkService
{
    public function __construct(private Repository $repository, private FetchBookmarksRepository $bookmarksRepository)
    {
    }

    public function fromRequest(UpdateBookmarkRequest $request): void
    {
        $data = UpdateBookmarkDataBuilder::fromRequest($request)->build();

        $bookmark = $this->bookmarksRepository->findById($data->id, BookmarkQueryColumns::new()->userId()->tags());

        (new EnsureAuthorizedUserOwnsResource)($bookmark);

        if ($bookmark->tags->count() + $data->tags->count() > CreateBookmarkRequest::MAX_TAGS) {
            throw new HttpException(['message' => 'Cannot add more tags to bookmark'], Response::HTTP_BAD_REQUEST);
        }

        $this->repository->update($data);
    }
}
