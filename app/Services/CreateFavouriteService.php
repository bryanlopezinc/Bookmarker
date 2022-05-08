<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsBookmark;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\FavouritesRepository;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateFavouriteService
{
    public function __construct(private FavouritesRepository $repository, private BookmarksRepository $bookmarkRepository)
    {
    }

    public function create(ResourceID $bookmarkId): void
    {
        $bookmark = $this->bookmarkRepository->findById($bookmarkId, BookmarkQueryColumns::new()->userId()->id());

        $userId = UserID::fromAuthUser();

        if ($bookmark === false) {
            throw new NotFoundHttpException();
        }

        (new EnsureAuthorizedUserOwnsBookmark)($bookmark);

        if ($this->repository->exists($bookmarkId, $userId)) {
            throw new HttpException(Response::HTTP_CONFLICT);
        }

        $this->repository->create($bookmarkId, $userId);
    }
}
