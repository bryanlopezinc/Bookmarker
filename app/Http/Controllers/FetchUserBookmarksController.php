<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\FetchUserBookmarksRequestData;
use App\Http\Requests\FetchUserBookmarksRequest;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\BookmarksRepository as Repository;

final class FetchUserBookmarksController
{
    public function __invoke(FetchUserBookmarksRequest $request, Repository $repository): PaginatedResourceCollection
    {
        $result = $repository->userBookmarks(FetchUserBookmarksRequestData::fromRequest($request));

        $result->appends('per_page', $request->validated('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, BookmarkResource::class);
    }
}
