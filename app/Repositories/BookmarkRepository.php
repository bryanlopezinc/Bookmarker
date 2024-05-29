<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bookmark as Model;
use App\Models\Favorite;
use App\Models\Scopes\HasDuplicatesScope;
use App\Models\Scopes\IsHealthyScope;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

class BookmarkRepository
{
    /**
     * @return Paginator<Model>
     */
    public function fetchPossibleDuplicates(Model $bookmark, int $userID, PaginationData $pagination): Paginator
    {
        $model = new Model();

        return Model::query()
            ->select(['bookmarks.id', 'public_id', 'description', 'title', 'url', 'preview_image_url', 'user_id', 'source_id', 'bookmarks.created_at'])
            ->tap(new HasDuplicatesScope())
            ->tap(new IsHealthyScope())
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
