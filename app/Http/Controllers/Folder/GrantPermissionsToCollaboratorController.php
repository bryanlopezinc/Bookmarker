<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\FolderPermissions as Permissions;
use App\Rules\ResourceIdRule;
use App\Services\Folder\GrantPermissionsToCollaboratorService as Service;
use App\ValueObjects\ResourceID as FolderID;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GrantPermissionsToCollaboratorController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'user_id' => $idRules = ['required', new ResourceIdRule],
            'folder_id' => $idRules,
            'permissions' => ['required', 'array', Rule::in([
                'addBookmarks',
                'removeBookmarks',
                'inviteUser'
            ])],
            'permissions.*' => ['filled', 'distinct:strict'],
        ]);

        $service->grant(
            new UserID((int)$request->input('user_id')),
            FolderID::fromRequest($request, 'folder_id'),
            Permissions::fromRequest($request, 'permissions')
        );

        return response()->json();
    }
}
