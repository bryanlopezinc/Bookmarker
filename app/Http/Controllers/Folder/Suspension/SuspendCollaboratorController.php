<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Suspension;

use App\Http\Handlers\SuspendCollaborator\Handler;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SuspendCollaboratorController
{
    public function __invoke(
        Request $request,
        Handler $service,
        string $folderId,
        string $collaboratorId
    ): JsonResponse {
        $maxSuspensionDuration = setting('MAX_SUSPENSION_DURATION_IN_HOURS');

        $folderId = FolderPublicId::fromRequest($folderId);
        $collaboratorId = UserPublicId::fromRequest($collaboratorId);

        $request->validate(['duration' => ['sometimes', 'int', 'min:1', "max:{$maxSuspensionDuration}"]]);

        $service->handle(
            $folderId,
            $collaboratorId,
            $request->input('duration'),
            User::fromRequest($request)
        );

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
