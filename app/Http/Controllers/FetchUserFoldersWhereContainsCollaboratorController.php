<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FilterUserCollaborationResource;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\PaginationData;
use App\Repositories\Folder\FetchUserFoldersWhereContainsCollaboratorRepository as Repository;
use App\Rules\UserCollaborationFieldsRule;
use App\ValueObjects\UserId;

final class FetchUserFoldersWhereContainsCollaboratorController
{
    public function __invoke(Request $request, Repository $repository): ResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
            'fields' => ['sometimes', new UserCollaborationFieldsRule()]
        ]);

        $result = $repository->get(
            UserId::fromAuthUser()->value(),
            intval($request->route('collaborator_id')),
            PaginationData::fromRequest($request),
        );

        return new ResourceCollection($result, FilterUserCollaborationResource::class);
    }
}
