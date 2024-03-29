<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\UAC;
use App\Services\Folder\GrantPermissionsToCollaboratorService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GrantPermissionsToCollaboratorController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array', Rule::in([
                'addBookmarks',
                'removeBookmarks',
                'inviteUser',
                'updateFolder'
            ])],
            'permissions.*' => ['filled', 'distinct:strict'],
        ]);

        $service->grant(
            (int)$request->route('collaborator_id'),
            (int)$request->route('folder_id'),
            UAC::fromRequest($request, 'permissions')
        );

        return response()->json();
    }
}
