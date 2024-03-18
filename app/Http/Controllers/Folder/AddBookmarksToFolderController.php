<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\AddBookmarksToFolder\Handler;
use App\Http\Requests\AddBookmarksToFolderRequest as Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;

final class AddBookmarksToFolderController
{
    public function __invoke(Request $request, Handler $handler, string $folderId): JsonResponse
    {
        $handler->handle((int)$folderId, Data::fromRequest($request));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
