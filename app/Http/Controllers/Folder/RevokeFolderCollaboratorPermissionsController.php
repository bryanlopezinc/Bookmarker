<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\UAC;
use App\Services\Folder\RevokeFolderCollaboratorPermissionsService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RevokeFolderCollaboratorPermissionsController
{
    public function __invoke(Request $request, Service $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array', Rule::in([
                'addBookmarks',
                'removeBookmarks',
                'inviteUser'
            ])],
            'permissions.*' => ['filled', 'distinct:strict'],
        ]);

        $service->revokePermissions(
            (int)$collaboratorId,
            (int)$folderId,
            UAC::fromRequest($request, 'permissions')
        );

        return response()->json();
    }
}
