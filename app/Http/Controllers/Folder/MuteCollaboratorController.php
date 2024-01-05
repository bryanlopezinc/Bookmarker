<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\UnMuteCollaboratorService;
use App\ValueObjects\UserId;
use Illuminate\Http\JsonResponse;

final class MuteCollaboratorController
{
    public function post(MuteCollaboratorService $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $service(
            (int)$folderId,
            (int)$collaboratorId,
            UserId::fromAuthUser()->value()
        );

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function delete(UnMuteCollaboratorService $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $service(
            (int)$folderId,
            (int)$collaboratorId,
        );

        return response()->json(status: JsonResponse::HTTP_OK);
    }
}
