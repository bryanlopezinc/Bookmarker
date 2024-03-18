<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\HideFolderBookmarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HideFolderBookmarksController
{
    public function __invoke(Request $request, HideFolderBookmarksService $service, string $folderId): JsonResponse
    {
        $request->validate([
            'bookmarks'   => ['required', 'array', 'filled', 'max:50'],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ]);

        $service->hide(
            $request->input('bookmarks'),
            (int) $folderId
        );

        return response()->json();
    }
}
