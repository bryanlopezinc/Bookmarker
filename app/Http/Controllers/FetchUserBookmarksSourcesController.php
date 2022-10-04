<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\SourceResource;
use App\PaginationData;
use App\Repositories\FetchUserBookmarksSourcesRepository as Repository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fetch all the websites user has bookmarked a page from.
 */
final class FetchUserBookmarksSourcesController
{
    public function __invoke(Request $request, Repository $repository): AnonymousResourceCollection
    {
        $request->validate(
            PaginationData::new()->maxPerPage(setting('PER_PAGE_BOOKMARKS_SOURCES'))->asValidationRules()
        );

        return new PaginatedResourceCollection(
            $repository->get(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            SourceResource::class
        );
    }
}
