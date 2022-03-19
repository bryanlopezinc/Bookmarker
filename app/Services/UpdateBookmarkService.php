<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Response;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Repositories\FindBookmarksRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Repositories\UpdateBookmarkRepository as Repository;
use App\DataTransferObjects\Builders\UpdateBookmarkDataBuilder;
use App\BookmarkColumns;
use App\Http\Requests\CreateBookmarkRequest;
use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateBookmarkService
{
    public function __construct(private Repository $repository, private FindBookmarksRepository $findBookmarks)
    {
    }

    public function fromRequest(UpdateBookmarkRequest $request): void
    {
        $data = UpdateBookmarkDataBuilder::fromRequest($request)->build();

        $bookmark = $this->findBookmarks->findById($data->id, BookmarkColumns::new()->userId()->tags());

        if ($bookmark === false) {
            throw new NotFoundHttpException();
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        if ($bookmark->tags->count() + $data->tags->count() > CreateBookmarkRequest::MAX_TAGS) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Cannot add more tags to bookmark');
        }

        $this->repository->update($data);
    }
}
