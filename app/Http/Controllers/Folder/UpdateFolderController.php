<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Http\Handlers\UpdateFolder\Handler;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Http\Requests\ToggleFolderFeatureRequest;
use App\Services\Folder\ToggleFolderFeature;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(Request $request, Handler $requestHandler, string $folderId): JsonResponse
    {
        $requestHandler->handle((int) $folderId, UpdateFolderRequestData::fromRequest($request));

        return new JsonResponse();
    }

    public function updateAction(
        ToggleFolderFeatureRequest $request,
        ToggleFolderFeature $service,
        string $folderId
    ): JsonResponse {
        $service->fromRequest($request, (int) $folderId);

        return new JsonResponse();
    }
}
