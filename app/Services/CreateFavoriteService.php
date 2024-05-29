<?php

declare(strict_types=1);

namespace App\Services;

use App\Collections\BookmarkPublicIdsCollection;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\HttpException;
use App\Jobs\CheckBookmarksHealth;
use App\Models\Bookmark;
use App\Models\Favorite;
use App\Models\Scopes\WherePublicIdScope;
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
        $bookmarkIDsCollection = BookmarkPublicIdsCollection::fromRequest($bookmarkIDs)->values();

        $bookmarks = Bookmark::query()
            ->withCasts(['isUserFavorite' => 'boolean'])
            ->select([
                'user_id',
                'id',
                'url',
                'public_id',
                'isUserFavorite' => Favorite::query()
                    ->select('id')
                    ->where('user_id', $authUserId)
                    ->whereColumn('bookmark_id', 'bookmarks.id')
            ])
            ->tap(new WherePublicIdScope($bookmarkIDsCollection))
            ->get()
            ->each(function (Bookmark $bookmark) {
                BookmarkNotFoundException::throwIfDoesNotBelongToAuthUser($bookmark);
            })
            ->tap(function (Collection $bookmarks) {
                $favorites = $bookmarks->filter->isUserFavorite;

                if ($favorites->isNotEmpty()) {
                    throw HttpException::conflict([
                        'message'  => 'FavoritesAlreadyExists',
                        'conflict' => $favorites->map(fn (Bookmark $bookmark) => $bookmark->public_id->present())->values()->all()
                    ]);
                }
            })
            ->tap(function (Collection $bookmarks) use ($bookmarkIDs) {
                if (count($bookmarkIDs) !== $bookmarks->count()) {
                    throw new BookmarkNotFoundException();
                }
            });

        $this->repository->createMany($bookmarks->pluck('id')->all(), $authUserId);

        dispatch(new CheckBookmarksHealth($bookmarks));
    }
}
