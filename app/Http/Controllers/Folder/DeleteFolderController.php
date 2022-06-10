<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\DeleteFolderService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFolderController
{
    public function __invoke(Request $request, DeleteFolderService $service): JsonResponse
    {
        $request->validate([
            'folder' => ['required', new ResourceIdRule]
        ]);

        $service->delete(ResourceID::fromRequest($request, 'folder'));

        return response()->json();
    }
}
