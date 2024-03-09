<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\UpdateFolder\Handler;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Http\Requests\UpdateCollaboratorActionRequest;
use App\Services\Folder\ToggleFolderFeature;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(Request $request, Handler $requestHandler, string $folderId): JsonResponse
    {
        $requestHandler->handle((int) $folderId);

        return new JsonResponse();
    }

    public function updateAction(
        UpdateCollaboratorActionRequest $request,
        ToggleFolderFeature $service
    ): JsonResponse {
        $service->fromRequest($request);

        return new JsonResponse();
    }
}
