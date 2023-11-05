<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportBookmarkRequest;
use App\Services\ImportBookmarksService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class ImportBookmarkController
{
    public function __invoke(ImportBookmarkRequest $request, Service $service): JsonResponse
    {
        $requestId = $request->input('request_id');

        if (Cache::has($requestId)) {
            return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
        }

        $service->fromRequest($request);

        Cache::put($requestId, true, now()->addMinutes(30));

        return response()->json(['message' => 'success'], Response::HTTP_PROCESSING);
    }
}
