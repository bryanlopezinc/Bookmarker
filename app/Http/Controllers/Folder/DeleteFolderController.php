<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\DeleteFolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFolderController
{
    public function __invoke(Request $request, DeleteFolderService $service): JsonResponse
    {
        $request->validate([
            'delete_bookmarks' => ['nullable', 'boolean']
        ]);

        $folderId = (int)$request->route('folder_id');

        if ($request->boolean('delete_bookmarks')) {
            $service->deleteRecursive($folderId);
        } else {
            $service->delete($folderId);
        }

        return response()->json();
    }
}
