<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bookmark as Model;
use App\Exceptions\BookmarkNotFoundException;
use App\Models\Favorite;
use App\PaginationData;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class BookmarkRepository
{
    /**
     * @throws BookmarkNotFoundException
     */
    public function findById(int $bookmarkId, array $attributes = []): Model
    {
        $result = $this->findManyById([$bookmarkId], $attributes);

        if ($result->isEmpty()) {
            throw new BookmarkNotFoundException();
        }

        return $result->sole();
    }

    /**
     * @param array<int> $ids
     *
     * @return Collection<Model>
     */
    public function findManyById(array $ids, array $attributes = []): Collection
    {
        return Model::WithQueryOptions($attributes)
            ->whereIn('bookmarks.id', collect($ids)->unique()->all())
            ->get()
            ->pipeInto(Collection::class);
    }

    /**
     * @return Paginator<Model>
     */
    public function fetchPossibleDuplicates(Model $bookmark, int $userID, PaginationData $pagination): Paginator
    {
        $model = new Model;

        return Model::WithQueryOptions()
            ->addSelect([
                'isUserFavorite' => Favorite::query()
                    ->select('id')
                    ->whereRaw("bookmark_id = {$model->qualifyColumn('id')}")
            ])
            ->where('url_canonical_hash', $bookmark->url_canonical_hash)
            ->where('bookmarks.user_id', $userID)
            ->whereNotIn('bookmarks.id', [$bookmark->id])
            ->simplePaginate($pagination->perPage(), page: $pagination->page());
    }
}
