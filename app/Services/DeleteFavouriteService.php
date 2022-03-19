<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FavouritesRepository;
use App\Repositories\FindBookmarksRepository;
use App\ValueObjects\ResourceId;
use App\ValueObjects\UserId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DeleteFavouriteService
{
    public function __construct(private FavouritesRepository $repository, private FindBookmarksRepository $bookmarkRepository)
    {
    }

    public function delete(ResourceId $bookmarkId): void
    {
        $userId = UserId::fromAuthUser();

        if (!$this->repository->exists($bookmarkId, $userId)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }

        $this->repository->delete($bookmarkId, $userId);
    }
}
