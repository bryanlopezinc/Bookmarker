<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Repositories\FavouritesRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class DeleteUserFavouritesService
{
    public function __construct(private FavouritesRepository $repository)
    {
    }

    public function __invoke(ResourceIDsCollection $bookmarkIDs): void
    {
        $userId = UserID::fromAuthUser();

        if (!$this->repository->exists($bookmarkIDs, $userId)) {
            throw new HttpResponseException(response()->json(status: Response::HTTP_NOT_FOUND));
        }

        $this->repository->delete($bookmarkIDs, $userId);
    }
}
