<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Http\Requests\UpdateCollaboratorActionRequest;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use App\Services\Folder\UpdateFolderService;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(Request $request, UpdateFolderService $service): JsonResponse
    {
        $service->fromRequest($request);

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
