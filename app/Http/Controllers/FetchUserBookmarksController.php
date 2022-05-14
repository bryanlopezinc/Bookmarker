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
        $request->validate(PaginationData::new()->asValidationRules());

        return new PaginatedResourceCollection(
            $repository->userBookmarks(FetchUserBookmarksRequestData::fromRequest($request)),
            BookmarkResource::class
        );
    }
}
