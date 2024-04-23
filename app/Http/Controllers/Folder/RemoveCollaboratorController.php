<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\RemoveCollaboratorData;
use App\Http\Handlers\RemoveCollaborator\Handler;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveCollaboratorController
{
    public function __invoke(Request $request, Handler $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $request->validate(['ban' => ['sometimes', 'boolean']]);

        $service->handle(
            new RemoveCollaboratorData(
                UserPublicId::fromRequest($collaboratorId),
                FolderPublicId::fromRequest($folderId),
                $request->boolean('ban'),
                User::fromRequest($request)
            )
        );

        return new JsonResponse();
    }
}
