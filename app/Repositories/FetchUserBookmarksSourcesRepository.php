<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\SiteBuilder;
use App\ValueObjects\UserID;
use App\DataTransferObjects\WebSite;
use App\Models\WebSite as Model;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

/**
 * Get all the websites a user has bookmarked a page from.
 */
final class FetchUserBookmarksSourcesRepository
{
    /**
     * @return Paginator<WebSite>
     */
    public function get(UserID $userId, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result = Model::select('sites.id', 'host', 'name')
            ->join('bookmarks', 'sites.id', '=', 'bookmarks.site_id')
            ->groupBy('sites.id')
            ->where('user_id', $userId->toInt())
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return $result->setCollection(
            $result->getCollection()->map(fn (Model $model) => SiteBuilder::fromModel($model)->build())
        );
    }
}
