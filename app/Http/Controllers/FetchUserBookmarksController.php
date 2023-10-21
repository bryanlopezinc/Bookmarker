<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FetchUserBookmarksRequest as Request;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Jobs\CheckBookmarksHealth;
use App\Services\FetchUserBookmarksService as Service;

final class FetchUserBookmarksController
{
    public function __invoke(Request $request, Service $service): PaginatedResourceCollection
    {
        $result = $service->fromRequest($request);

        dispatch(new CheckBookmarksHealth($result->getCollection()));

        return new PaginatedResourceCollection($result, BookmarkResource::class);
    }
}
