<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\LeaveFolder\Handler as Service;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveFolderCollaborationController
{
    public function __invoke(Request $request, Service $service, string $folderId): JsonResponse
    {
        $service->handle(FolderPublicId::fromRequest($folderId), User::fromRequest($request));

        return new JsonResponse();
    }
}
