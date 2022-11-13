<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FilterUserCollaborationResource;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\PaginationData;
use App\Repositories\Folder\FetchUserFoldersWhereContainsCollaboratorRepository as Repository;
use App\Rules\ResourceIdRule;
use App\Rules\UserCollaborationFieldsRule;
use App\ValueObjects\UserID;

final class FetchUserFoldersWhereContainsCollaboratorController
{
    public function __invoke(Request $request, Repository $repository): ResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
            'collaborator_id' => ['required', new ResourceIdRule],
            'fields' => ['sometimes', new UserCollaborationFieldsRule]
        ]);

        $result = $repository->get(
            UserID::fromAuthUser(),
            new UserID((int)$request->input('collaborator_id')),
            PaginationData::fromRequest($request),
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new ResourceCollection($result, FilterUserCollaborationResource::class);
    }
}
