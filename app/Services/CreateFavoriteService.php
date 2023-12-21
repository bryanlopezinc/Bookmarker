<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Repositories\FavoriteRepository;
use Illuminate\Support\Collection;

final class CreateFavoriteService
{
    public function __construct(private FavoriteRepository $repository)
    {
    }

    /**
     * @param array<int> $bookmarkIDs
     */
    public function create(array $bookmarkIDs, int $authUserId): void
    {
        $bookmarks = Bookmark::query()
            ->withCasts(['isUserFavorite' => 'boolean'])
            ->select([
                'user_id',
                'id',
                'url',
                'isUserFavorite' => Favorite::query()
                    ->select('id')
                    ->where('user_id', $authUserId)
                    ->whereColumn('bookmark_id', 'bookmarks.id')
            ])
            ->whereIntegerInRaw('id', $bookmarkIDs)
            ->get()
            ->each(function (Bookmark $bookmark) {
                BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
            })
            ->tap(function (Collection $bookmarks) {
                $favorites = $bookmarks->filter->isUserFavorite->pluck('id');

                if ($favorites->isNotEmpty()) {
                    throw HttpException::conflict([
                        'message'  => 'FavoritesAlreadyExists',
                        'conflict' => $favorites->all()
                    ]);
                }
            })
            ->tap(function (Collection $bookmarks) use ($bookmarkIDs) {
                if (count($bookmarkIDs) !== $bookmarks->count()) {
                    throw new BookmarkNotFoundException();
                }
            });

        $this->repository->createMany($bookmarkIDs, $authUserId);

        dispatch(new CheckBookmarksHealth($bookmarks));
    }
}
