<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Repositories\FavouritesRepository;
use App\ValueObjects\UserID;

final class DeleteUserFavouritesService
{
    public function __construct(private FavouritesRepository $repository)
    {
    }

    public function __invoke(ResourceIDsCollection $bookmarkIDs): void
    {
        $userId = UserID::fromAuthUser();

        if (!$this->repository->exists($bookmarkIDs, $userId)) {
            throw  HttpException::notFound(['message' => 'favourites does not exists']);
        }

        $this->repository->delete($bookmarkIDs, $userId);
    }
}
