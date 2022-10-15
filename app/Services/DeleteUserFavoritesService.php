<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Repositories\FavoriteRepository;
use App\ValueObjects\UserID;

final class DeleteUserFavoritesService
{
    public function __construct(private FavoriteRepository $repository)
    {
    }

    public function __invoke(ResourceIDsCollection $bookmarkIDs): void
    {
        $userId = UserID::fromAuthUser();

        if (!$this->repository->containsAll($bookmarkIDs, $userId)) {
            throw  HttpException::notFound(['message' => 'favorites does not exists']);
        }

        $this->repository->delete($bookmarkIDs, $userId);
    }
}
