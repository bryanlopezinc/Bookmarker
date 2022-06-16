<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Enums\UserFoldersSortCriteria;
use App\Http\Requests\FetchUserFoldersRequest;
use App\Http\Resources\FolderResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\UsersFoldersRepository;
use App\ValueObjects\UserID;

final class FetchUserFoldersController
{
    public function __invoke(FetchUserFoldersRequest $request, UsersFoldersRepository $repository): PaginatedResourceCollection
    {
        $request->validate([...PaginationData::new()->asValidationRules()]);

        $result = $repository->fetch(
            UserID::fromAuthUser(),
            PaginationData::fromRequest($request),
            UserFoldersSortCriteria::fromRequest($request)
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, FolderResource::class);
    }
}
