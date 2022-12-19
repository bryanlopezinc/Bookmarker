<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\Folder\HideFolderBookmarksService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HideFolderBookmarksController
{
    public function __invoke(Request $request, HideFolderBookmarksService $service): JsonResponse
    {
        $request->validate([
            'folder_id' => ['required', new ResourceIdRule()],
            'bookmarks' => ['required', 'array', 'filled', join(':', ['max', setting('MAX_POST_HIDE_BOOKMARKS')])],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ]);

        $service->hide(
            ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')),
            ResourceID::fromRequest($request, 'folder_id')
        );

        return response()->json();
    }
}
