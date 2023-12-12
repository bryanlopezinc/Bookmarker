<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderBookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Services\Folder\FetchFolderBookmarksService;
use Illuminate\Http\Request;

final class FetchFolderBookmarksController
{
    public function __invoke(Request $request, FetchFolderBookmarksService $service): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $request->validate(['folder_password' => ['sometimes', 'filled', 'string']]);

        $result = $service($request);

        return new PaginatedResourceCollection($result, FolderBookmarkResource::class);
    }
}
