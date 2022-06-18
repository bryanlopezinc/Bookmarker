<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\FavouritesRepository;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

final class CreateFavouriteService
{
    public function __construct(
        private FavouritesRepository $repository,
        private FetchBookmarksRepository $bookmarkRepository
    ) {
    }

    public function create(ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarks = $this->bookmarkRepository->findManyById($bookmarkIDs, BookmarkQueryColumns::new()->userId()->id());

        //throw exception if some bookmarkIDs does not exists.
        if ($bookmarks->count() !== $bookmarkIDs->count()) {
            $this->throwNotFoundException();
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        $userId = UserID::fromAuthUser();

        $duplicates = $this->repository->duplicates($userId, $bookmarkIDs);

        if ($duplicates->isNotEmpty()) {
            $this->throwConflictException();
        }

        $this->repository->createMany($bookmarkIDs, $userId);
    }

    private function throwNotFoundException(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Bookmarks does not exists'
        ], Response::HTTP_NOT_FOUND));
    }

    private function throwConflictException(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Bookmarks already exists in favourites'
        ], Response::HTTP_CONFLICT));
    }
}
