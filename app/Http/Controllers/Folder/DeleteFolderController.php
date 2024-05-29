<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\DeleteFolderService;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFolderController
{
    public function __invoke(Request $request, DeleteFolderService $service, string $folderId): JsonResponse
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate([
            'delete_bookmarks' => ['nullable', 'boolean']
        ]);

        if ($request->boolean('delete_bookmarks')) {
            $service->deleteRecursive($folderId);
        } else {
            $service->delete($folderId);
        }

        return response()->json();
    }
}
