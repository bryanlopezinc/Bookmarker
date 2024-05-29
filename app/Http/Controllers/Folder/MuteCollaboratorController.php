<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Models\User;
use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\UnMuteCollaboratorService;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
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
            FolderPublicId::fromRequest($folderId),
            UserPublicId::fromRequest($collaboratorId),
            User::fromRequest($request)->id,
            $request->integer('mute_until')
        );

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function delete(UnMuteCollaboratorService $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $service(
            FolderPublicId::fromRequest($folderId),
            UserPublicId::fromRequest($collaboratorId),
        );

        return response()->json(status: JsonResponse::HTTP_OK);
    }
}
