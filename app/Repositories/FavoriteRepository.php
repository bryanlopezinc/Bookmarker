<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Favorite;
use App\Models\Bookmark as Model;
use App\Models\Scopes\HasDuplicatesScope;
use App\Models\Scopes\IsHealthyScope;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

final class FavoriteRepository
{
    public function create(int $bookmarkId, int $userId): void
    {
        $this->createMany([$bookmarkId], $userId);
    }

    public function createMany(array $bookmarkIds, int $userId): void
    {
        Favorite::insert(collect($bookmarkIds)->map(fn (int $bookmarkID) => [
            'user_id'     => $userId,
            'bookmark_id' => $bookmarkID
        ])->all());
    }

    /**
     * @return Paginator<Model>
     */
    public function get(int $userId, PaginationData $pagination): Paginator
    {
        $query =  Model::query()
            ->select(['bookmarks.id', 'public_id', 'description', 'title', 'url', 'preview_image_url', 'bookmarks.user_id', 'source_id', 'bookmarks.created_at'])
            ->tap(new HasDuplicatesScope())
            ->tap(new IsHealthyScope())
            ->with(['source', 'tags'])
            ->join('favorites', 'favorites.bookmark_id', '=', 'bookmarks.id')
            ->where('favorites.user_id', $userId)
            ->latest('favorites.id');

        return $query->simplePaginate($pagination->perPage(), page: $pagination->page());
    }
}
