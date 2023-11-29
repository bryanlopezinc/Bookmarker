<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\RemoveCollaboratorService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveCollaboratorController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'ban' => ['sometimes', 'boolean']
        ]);

        $service->revokeUserAccess(
            (int)$request->route('folder_id'),
            (int)$request->route('collaborator_id'),
            $request->boolean('ban')
        );

        return response()->json();
    }
}
