<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\RemoveFolderBookmarks\Handler;
use App\Rules\ResourceIdRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DataTransferObjects\RemoveFolderBookmarksRequestData as Data;

final class RemoveBookmarksFromFolderController
{
    public function __invoke(Request $request, Handler $requestHandler, string $folderId): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'max:50'],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict'],
        ]);

        $requestHandler->handle((int) $folderId, Data::fromRequest($request));

        return new JsonResponse();
    }
}
