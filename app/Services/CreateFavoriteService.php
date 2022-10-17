<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\BookmarksCollection;
use App\Collections\ResourceIDsCollection;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\BookmarkAttributes;
use App\Repositories\FavoriteRepository;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\UserID;

final class CreateFavoriteService
{
    public function __construct(
        private FavoriteRepository $repository,
        private FetchBookmarksRepository $bookmarkRepository
    ) {
    }

    public function create(ResourceIDsCollection $bookmarkIDs): void
    {
        $userId = UserID::fromAuthUser();

        $bookmarks = $this->bookmarkRepository->findManyById($bookmarkIDs, BookmarkAttributes::only('user_id,id,url'));

        $allBookmarksExists = $bookmarkIDs->count() === $bookmarks->count();

        if (!$allBookmarksExists) {
            throw HttpException::notFound(['message' => 'Bookmarks does not exists']);
        }

        $bookmarks->each(new EnsureAuthorizedUserOwnsResource);

        if ($this->repository->contains($bookmarkIDs, $userId)) {
            throw HttpException::conflict(['message' => 'Bookmarks already exists in favorites']);
        }

        $this->repository->createMany($bookmarkIDs, $userId);

        dispatch(new CheckBookmarksHealth(new BookmarksCollection($bookmarks)));
    }
}
