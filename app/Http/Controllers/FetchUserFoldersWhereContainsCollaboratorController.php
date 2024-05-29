<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FilterUserCollaborationResource;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\User;
use App\PaginationData;
use App\Repositories\Folder\FetchUserFoldersWhereContainsCollaboratorRepository as Repository;
use App\Rules\UserCollaborationFieldsRule;
use App\ValueObjects\PublicId\UserPublicId;

final class FetchUserFoldersWhereContainsCollaboratorController
{
    public function __invoke(Request $request, Repository $repository, string $collaboratorId): ResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
            'fields' => ['sometimes', new UserCollaborationFieldsRule()]
        ]);

        $result = $repository->get(
            User::fromRequest($request)->id,
            UserPublicId::fromRequest($collaboratorId),
            PaginationData::fromRequest($request),
        );

        return new ResourceCollection($result, FilterUserCollaborationResource::class);
    }
}
