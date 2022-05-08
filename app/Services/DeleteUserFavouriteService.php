<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FavouritesRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DeleteUserFavouriteService
{
    public function __construct(private FavouritesRepository $repository)
    {
    }

    public function __invoke(ResourceID $bookmarkId): void
    {
        $userId = UserID::fromAuthUser();

        if (!$this->repository->exists($bookmarkId, $userId)) {
            throw new HttpException(Response::HTTP_FORBIDDEN);
        }

        $this->repository->delete($bookmarkId, $userId);
    }
}
