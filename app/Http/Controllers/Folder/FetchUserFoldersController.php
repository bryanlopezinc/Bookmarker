<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Enums\UserFoldersSortCriteria;
use App\Http\Requests\FetchUserFoldersRequest;
use App\Http\Resources\FilterFolderResource;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\PaginationData;
use App\Repositories\Folder\UserFoldersRepository;
use App\Rules\FolderFieldsRule;
use App\ValueObjects\UserId;

final class FetchUserFoldersController
{
    public function __invoke(FetchUserFoldersRequest $request, UserFoldersRepository $repository): ResourceCollection
    {
        $request->validate([
            ...PaginationData::new()->asValidationRules(),
            'fields' => ['sometimes', new FolderFieldsRule()]
        ]);

        $result = $repository->fetch(
            UserId::fromAuthUser()->value(),
            PaginationData::fromRequest($request),
            UserFoldersSortCriteria::fromRequest($request)
        );

        return new ResourceCollection($result, FilterFolderResource::class);
    }
}
