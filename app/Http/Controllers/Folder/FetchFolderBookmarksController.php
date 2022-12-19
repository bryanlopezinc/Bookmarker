<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderBookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderBookmarksService;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\Request;

final class FetchFolderBookmarksController
{
    public function __invoke(Request $request, FetchFolderBookmarksService $service): PaginatedResourceCollection
    {
        $request->validate([
            'folder_id' => ['required', new ResourceIdRule()],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service->fetch(
            ResourceID::fromRequest($request, 'folder_id'),
            PaginationData::fromRequest($request),
            UserID::fromAuthUser()
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, FolderBookmarkResource::class);
    }
}
