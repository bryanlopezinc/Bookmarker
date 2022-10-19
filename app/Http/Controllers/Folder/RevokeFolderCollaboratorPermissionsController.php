<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\UAC;
use App\Rules\ResourceIdRule;
use App\Services\Folder\RevokeFolderCollaboratorPermissionsService as Service;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RevokeFolderCollaboratorPermissionsController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'user_id' => $rules = ['required', new ResourceIdRule],
            'folder_id' => $rules,
            'permissions' => ['required', 'array', Rule::in([
                'addBookmarks',
                'removeBookmarks',
                'inviteUser'
            ])],
            'permissions.*' => ['filled', 'distinct:strict'],
        ]);

        $service->revokePermissions(
            new UserID((int) $request->input('user_id')),
            ResourceID::fromRequest($request, 'folder_id'),
            UAC::fromRequest($request, 'permissions')
        );

        return response()->json();
    }
}
