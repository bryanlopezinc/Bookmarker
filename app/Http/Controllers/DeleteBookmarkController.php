<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\ResourceIdRule;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarkService;

final class DeleteBookmarkController
{
    public function __invoke(Request $request, DeleteBookmarkService $service): JsonResponse
    {
        $request->validate([
            'id' => ['required', new ResourceIdRule]
        ]);

        $service->delete(ResourceID::fromRequest($request));

        return response()->json(status: 204);
    }
}
