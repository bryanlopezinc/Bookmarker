<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\LeaveFolderCollaborationService as Service;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveFolderCollaborationController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'folder_id' => ['required', new ResourceIdRule()]
        ]);

        $service->leave(ResourceID::fromRequest($request, 'folder_id'));

        return response()->json();
    }
}
