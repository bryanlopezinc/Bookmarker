<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\Services\Folder\CreateFolderService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateFolderController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service($request);

        return response()->json(status: Response::HTTP_CREATED);
    }
}
