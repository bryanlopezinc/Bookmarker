<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\RemoveFolderBookmarksService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveBookmarksFromFolderController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'max:50'],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict'],
            'folder'      => ['required', new ResourceIdRule()]
        ]);

        $service->remove(
            $request->collect('bookmarks')->map(fn ($id) => intval($id))->all(),
            $request->integer('folder')
        );

        return response()->json();
    }
}
