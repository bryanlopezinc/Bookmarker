<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookmarkNotFoundException;
use App\Http\CreateFavoritesResponse;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Repositories\FavoriteRepository;
use App\Repositories\BookmarkRepository;

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
    public function create(array $bookmarkIDs, int $authUserId): CreateFavoritesResponse
    {
        $bookmarks = $this->bookmarkRepository->findManyById($bookmarkIDs, ['user_id', 'id', 'url']);

        $exists = Favorite::where('user_id', $authUserId)
            ->whereIntegerInRaw('bookmark_id', $bookmarkIDs)
            ->get(['bookmark_id'])
            ->pluck('bookmark_id');

        $newFavorites = array_diff($bookmarkIDs, $exists->all());

        if (count($bookmarkIDs) !== $bookmarks->count()) {
            throw new BookmarkNotFoundException;
        }

        $bookmarks->each(function (Bookmark $bookmark) {
            BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
        });

        if (!empty($newFavorites)) {
            $this->repository->createMany($newFavorites, $authUserId);
        }

        dispatch(new CheckBookmarksHealth($bookmarks));

        return new CreateFavoritesResponse($newFavorites);
    }
}
