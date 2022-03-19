<?php

declare(strict_types=1);

namespace App\Repositories;

use App\BookmarkColumns;
use App\ValueObjects\UserId;
use App\Models\Bookmark as Model;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\FetchUserBookmarksRequestData;
use App\ValueObjects\ResourceId;
use App\ValueObjects\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

final class FetchUserBookmarksRepository
{
    /**
     * @return Paginator<Bookmark>
     */
    public function get(FetchUserBookmarksRequestData $data): Paginator
    {
        $query = Model::WithQueryOptions(new BookmarkColumns());

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
