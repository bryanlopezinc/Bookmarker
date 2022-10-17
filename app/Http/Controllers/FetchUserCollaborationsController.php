<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\PaginationData;
use App\Repositories\Folder\FetchUserCollaborationsRepository as Repository;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\UserCollaborationResource;
use App\ValueObjects\UserID;

final class FetchUserCollaborationsController
{
    public function __invoke(Request $request, Repository $repository): PaginatedResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $repository->get(
            UserID::fromAuthUser(),
            PaginationData::fromRequest($request),
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, UserCollaborationResource::class);
    }
}
