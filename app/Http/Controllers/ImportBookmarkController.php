<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportBookmarkRequest;
use App\Services\ImportBookmarksService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ImportBookmarkController
{
    public function __invoke(ImportBookmarkRequest $request, Service $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json(['message' => 'success'], Response::HTTP_PROCESSING);
    }
}
