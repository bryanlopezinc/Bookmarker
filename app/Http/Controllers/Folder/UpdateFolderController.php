<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Requests\CreateFolderRequest;
use App\Services\UpdateFolderService;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(CreateFolderRequest $request, UpdateFolderService $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json();
    }
}