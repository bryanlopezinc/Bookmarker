<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Services\UpdateBookmarkService;

final class UpdateBookmarkController
{
    public function __invoke(UpdateBookmarkRequest $request, UpdateBookmarkService $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json(['success']);
    }
}
