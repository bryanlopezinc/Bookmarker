<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\RemoveFolderBookmarks\Handler;
use App\Rules\ResourceIdRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveBookmarksFromFolderController
{
    public function __invoke(Request $request, Handler $requestHandler): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'max:50'],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict'],
            'folder'      => ['required', new ResourceIdRule()]
        ]);

        $requestHandler->handle(
            $request->collect('bookmarks')->map(fn ($id) => intval($id))->all(),
            $request->integer('folder')
        );

        return new JsonResponse();
    }
}
