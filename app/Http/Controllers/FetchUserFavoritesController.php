<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\BookmarksCollection;
use App\Http\Resources\BookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Jobs\CheckBookmarksHealth;
use App\PaginationData;
use App\Repositories\FavoriteRepository as Repository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUserFavoritesController
{
    public function __invoke(Request $request, Repository $repository): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $userFavorites = $repository->get(UserID::fromAuthUser(), PaginationData::fromRequest($request));

        dispatch(new CheckBookmarksHealth(new BookmarksCollection($userFavorites->getCollection())));

        return new PaginatedResourceCollection($userFavorites, BookmarkResource::class);
    }
}
