<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\HideFolderBookmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HideFolderBookmarksController
{
    public function __invoke(Request $request, HideFolderBookmarksService $service): JsonResponse
    {
        $request->validate([
            'folder_id'   => ['required', new ResourceIdRule()],
            'bookmarks'   => ['required', 'array', 'filled', join(':', ['max', setting('MAX_POST_HIDE_BOOKMARKS')])],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ]);

        $service->hide(
            $request->input('bookmarks'),
            $request->integer('folder_id')
        );

        return response()->json();
    }
}
