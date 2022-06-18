<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkQueryColumns;
use App\Repositories\FavouritesRepository;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\UserID;

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
            throw HttpException::notFound(['message' => 'Bookmarks does not exists']);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        $userId = UserID::fromAuthUser();

        $duplicates = $this->repository->duplicates($userId, $bookmarkIDs);

        if ($duplicates->isNotEmpty()) {
            throw HttpException::conflict(['message' => 'Bookmarks already exists in favourites']);
        }

        $this->repository->createMany($bookmarkIDs, $userId);
    }
}
