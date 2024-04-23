<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\LeaveFolderCollaborationService as Service;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveFolderCollaborationController
{
    public function __invoke(Request $request, Service $service, string $folderId): JsonResponse
    {
        $service->leave(FolderPublicId::fromRequest($folderId));

        return response()->json();
    }
}
