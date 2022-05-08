<?php

declare(strict_types=1);

namespace App\Repositories;

use App\ValueObjects\ResourceID;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\FetchUserBookmarksRequestData;
use App\QueryColumns\BookmarkQueryColumns as Columns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

final class BookmarksRepository
{
    public function findById(ResourceID $bookmarkId, Columns $columns = new Columns()): Bookmark|false
    {
        $model = Model::WithQueryOptions($columns)->whereKey($bookmarkId->toInt())->first();

        if (!$columns->has('id') && !is_null($model)) {
            $model->offsetUnset('id');
        }

        return is_null($model) ? false : BookmarkBuilder::fromModel($model)->build();
    }

    /**
     * @return Paginator<Bookmark>
     */
    public function userBookmarks(FetchUserBookmarksRequestData $data): Paginator
    {
        $query = Model::WithQueryOptions(new Columns());

        if ($data->hasCustomSite) {
            $query->where('site_id', $data->siteId->toInt());
        }

        if ($data->hasTag) {
            $query->whereHas('tags', function (Builder $builder) use ($data) {
                $builder->where('name', $data->tag->value);
            });
        }

        /** @var Paginator */
        $result = $query->where('user_id', $data->userId->toInt())->simplePaginate($data->pagination->perPage(), page: $data->pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $bookmark) => BookmarkBuilder::fromModel($bookmark)->build())
        );
    }
}
