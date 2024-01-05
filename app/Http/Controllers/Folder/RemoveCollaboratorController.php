<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\RemoveCollaboratorService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveCollaboratorController
{
    public function __invoke(Request $request, Service $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $request->validate([
            'ban' => ['sometimes', 'boolean']
        ]);

        $service->revokeUserAccess(
            (int)$folderId,
            (int)$collaboratorId,
            $request->boolean('ban')
        );

        return response()->json();
    }
}
