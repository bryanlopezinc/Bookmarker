<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\BookmarksRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUntaggedUserBookmarksController
{
    public function __invoke(Request $request, BookmarksRepository $repository)
    {
        $request->validate(PaginationData::rules());

        return new PaginatedResourceCollection(
            $repository->getUntaggedBookmarks(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            BookmarkResource::class
        );
    }
}
