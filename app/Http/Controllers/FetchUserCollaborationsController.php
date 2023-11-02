<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\PaginationData;
use App\Repositories\Folder\FetchUserCollaborationsRepository as Repository;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Http\Resources\FilterUserCollaborationResource;
use App\Rules\UserCollaborationFieldsRule;
use App\ValueObjects\UserId;

final class FetchUserCollaborationsController
{
    public function __invoke(Request $request, Repository $repository): ResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
            'fields' => ['sometimes', new UserCollaborationFieldsRule()]
        ]);

        $result = $repository->get(
            UserId::fromAuthUser()->value(),
            PaginationData::fromRequest($request),
        );

        return new ResourceCollection($result, FilterUserCollaborationResource::class);
    }
}
