<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\DeleteCollaboratorService as Service;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteCollaboratorController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'user_id' => $rules = ['required', new ResourceIdRule],
            'folder_id' => $rules
        ]);

        $service->revokeUserAccess(
            ResourceID::fromRequest($request, 'folder_id'),
            new UserID((int)$request->input('user_id'))
        );

        return response()->json();
    }
}
