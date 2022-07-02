<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\DeleteFolderService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFolderController
{
    public function __invoke(Request $request, DeleteFolderService $service): JsonResponse
    {
        $request->validate([
            'folder' => ['required', new ResourceIdRule],
            'delete_bookmarks' => ['nullable', 'boolean']
        ]);

        $folderID = ResourceID::fromRequest($request, 'folder');

        $shouldDeleteFolderBookmarks = $request->boolean('delete_bookmarks');

        if ($shouldDeleteFolderBookmarks) {
            $service->deleteRecursive($folderID);
        } else {
            $service->delete($folderID);
        }

        return response()->json();
    }
}
