<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\FetchFolderBookmarks\Handler;
use App\Http\Resources\FolderBookmarkResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use Illuminate\Http\Request;
use App\DataTransferObjects\FetchFolderBookmarksRequestData as Data;

final class FetchFolderBookmarksController
{
    public function __invoke(Request $request, Handler $handler, string $folderId): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $request->validate(['folder_password' => ['sometimes', 'filled', 'string']]);

        $result = $handler->handle((int) $folderId, Data::fromRequest($request));

        return new PaginatedResourceCollection($result, FolderBookmarkResource::class);
    }
}
