<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Services\CreateBookmarkService;
use App\Http\Requests\CreateOrUpdateBookmarkRequest;

final class CreateBookmarkController
{
    public function __invoke(CreateOrUpdateBookmarkRequest $request, CreateBookmarkService $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }
}
