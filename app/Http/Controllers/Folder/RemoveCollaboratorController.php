<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\RemoveCollaboratorService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveCollaboratorController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'user_id'   => $rules = ['required', new ResourceIdRule()],
            'folder_id' => $rules,
            'ban'       => ['sometimes', 'boolean']
        ]);

        $service->revokeUserAccess(
            $request->integer('folder_id'),
            $request->integer('user_id'),
            $request->boolean('ban')
        );

        return response()->json();
    }
}
