<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Repositories\UsersFoldersRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchUserFoldersController
{
    public function __invoke(Request $request, UsersFoldersRepository $repository): PaginatedResourceCollection
    {
        $request->validate([...PaginationData::new()->asValidationRules()]);

        $result = $repository->fetch(UserID::fromAuthUser(), PaginationData::fromRequest($request));

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, FolderResource::class);
    }
}
