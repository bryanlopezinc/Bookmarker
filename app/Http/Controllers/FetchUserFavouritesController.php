<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\FavouriteRepository as Repository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUserFavouritesController
{
    public function __invoke(Request $request, Repository $repository): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        return new PaginatedResourceCollection(
            $repository->get(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            BookmarkResource::class
        );
    }
}
