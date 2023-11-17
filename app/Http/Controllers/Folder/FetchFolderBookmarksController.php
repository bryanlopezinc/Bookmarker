<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderBookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderBookmarksService;
use Illuminate\Http\Request;

final class FetchFolderBookmarksController
{
    public function __invoke(Request $request, FetchFolderBookmarksService $service): PaginatedResourceCollection
    {
        $authUser = auth();

        $request->validate([
            'folder_id' => ['required', new ResourceIdRule()],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service->fetch(
            $request->integer('folder_id'),
            PaginationData::fromRequest($request),
            $authUser->check() ? (int) $authUser->id() : null
        );

        return new PaginatedResourceCollection($result, FolderBookmarkResource::class);
    }
}
