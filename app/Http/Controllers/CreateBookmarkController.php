<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Services\CreateBookmarkService;
use App\Http\Requests\CreateBookmarkRequest;

final class CreateBookmarkController
{
    public function __invoke(CreateBookmarkRequest $request, CreateBookmarkService $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json(status: 201);
    }
}
