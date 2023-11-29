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
        $authUser = auth();

        $request->validate(PaginationData::new()->asValidationRules());

        $result = $service->fetch(
            intval($request->route('folder_id')),
            PaginationData::fromRequest($request),
            $authUser->check() ? (int) $authUser->id() : null
        );

        return new PaginatedResourceCollection($result, FolderBookmarkResource::class);
    }
}
