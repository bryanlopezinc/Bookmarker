<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\WebSiteResource;
use App\PaginationData;
use App\Repositories\FetchUserSitesRepository as Repository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FetchUserSitesController
{
    public function __invoke(Request $request, Repository $repository): AnonymousResourceCollection
    {
        $request->validate(PaginationData::new()->maxPerPage(50)->asValidationRules());

        return new PaginatedResourceCollection(
            $repository->get(UserID::fromAuthUser(), PaginationData::fromRequest($request)),
            WebSiteResource::class
        );
    }
}
