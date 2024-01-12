<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\UnMuteCollaboratorService;
use App\ValueObjects\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MuteCollaboratorController
{
    public function post(
        Request $request,
        MuteCollaboratorService $service,
        string $folderId,
        string $collaboratorId
    ): JsonResponse {
        $request->validate(['mute_until' => ['sometimes', 'int', 'min:1', 'max:744']]);

        $service(
            (int)$folderId,
            (int)$collaboratorId,
            UserId::fromAuthUser()->value(),
            $request->integer('mute_until')
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
