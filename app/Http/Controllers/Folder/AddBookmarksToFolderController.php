<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Requests\AddBookmarksToFolderRequest;
use App\Services\Folder\AddBookmarksToFolderService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AddBookmarksToFolderController
{
    public function __invoke(AddBookmarksToFolderRequest $request, Service $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json(status: Response::HTTP_CREATED);
    }
}
