<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\UpdateFolder\Handler;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Http\Requests\UpdateCollaboratorActionRequest;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(Request $request, Handler $requestHandler): JsonResponse
    {
        $requestHandler->handle((int) $request->route('folder_id'));//@phpstan-ignore-line

        return new JsonResponse();
    }

    public function updateAction(
        UpdateCollaboratorActionRequest $request,
        ToggleFolderCollaborationRestriction $service
    ): JsonResponse {
        $service->fromRequest($request);

        return new JsonResponse();
    }
}
