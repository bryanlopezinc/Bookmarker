<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Models\User;
use App\UAC;
use App\Services\Folder\GrantPermissionsToCollaboratorService as Service;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GrantPermissionsToCollaboratorController
{
    public function __invoke(Request $request, Service $service, string $folderId, string $collaboratorId): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array', Rule::in(UAC::validExternalIdentifiers())],
            'permissions.*' => ['filled', 'distinct:strict'],
        ]);

        $service->grant(
            UserPublicId::fromRequest($collaboratorId),
            FolderPublicId::fromRequest($folderId),
            UAC::fromRequest($request, 'permissions'),
            User::fromRequest($request)->id
        );

        return response()->json();
    }
}
