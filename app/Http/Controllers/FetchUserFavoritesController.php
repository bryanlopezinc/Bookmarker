<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\UserFavoriteResource;
use App\Jobs\CheckBookmarksHealth;
use App\Models\User;
use App\PaginationData;
use App\Repositories\FavoriteRepository as Repository;
use Illuminate\Http\Request;

final class FetchUserFavoritesController
{
    public function __invoke(Request $request, Repository $repository): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $userFavorites = $repository->get(User::fromRequest($request)->id, PaginationData::fromRequest($request));

        dispatch(new CheckBookmarksHealth($userFavorites->getCollection()));

        return new PaginatedResourceCollection($userFavorites, UserFavoriteResource::class);
    }
}
