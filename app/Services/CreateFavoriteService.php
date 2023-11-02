<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Repositories\FavoriteRepository;
use App\Repositories\BookmarkRepository;
use App\ValueObjects\UserID;

final class CreateFavoriteService
{
    public function __construct(
        private FavoriteRepository $repository,
        private BookmarkRepository $bookmarkRepository
    ) {
    }

    /**
     * @param array<int> $bookmarkIDs
     */
    public function create(array $bookmarkIDs): void
    {
        $userId = UserID::fromAuthUser();

        $bookmarks = $this->bookmarkRepository->findManyById($bookmarkIDs, ['user_id', 'id', 'url']);

        $allBookmarksExists = count($bookmarkIDs) === $bookmarks->count();

        if (!$allBookmarksExists) {
            throw new BookmarkNotFoundException;
        }

        $bookmarks->each(function (Bookmark $bookmark) {
            BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
        });

        if ($this->repository->contains($bookmarkIDs, $userId->value())) {
            throw HttpException::conflict(['message' => 'BookmarksAlreadyExists']);
        }

        $this->repository->createMany($bookmarkIDs, $userId->value());

        dispatch(new CheckBookmarksHealth($bookmarks));
    }
}
