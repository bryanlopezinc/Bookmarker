<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Suspension;

use App\Http\Handlers\ReInstateSuspendedCollaborator\Handler;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReInstateSuspendedCollaboratorController
{
    public function __invoke(Request $request, Handler $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $collaboratorId = UserPublicId::fromRequest($collaboratorId);

        $service->handle(
            $folderId,
            $collaboratorId,
            User::fromRequest($request)
        );

        return new JsonResponse();
    }
}
