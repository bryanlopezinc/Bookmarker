<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\UserBookmarksFilters;
use App\Http\Requests\FetchUserBookmarksRequest;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\UserBookmarksRepository as Repository;
use App\ValueObjects\UserID;

final class FetchUserBookmarksController
{
    public function __invoke(FetchUserBookmarksRequest $request, Repository $repository): PaginatedResourceCollection
    {
        $result = $repository->fetch(UserID::fromAuthUser(), UserBookmarksFilters::fromRequest($request));

        $result->appends('per_page', $request->validated('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, BookmarkResource::class);
    }
}
