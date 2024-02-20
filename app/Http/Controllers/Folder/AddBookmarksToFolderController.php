<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Actions\AddBookmarksToFolder\RequestHandler;
use App\Http\Requests\AddBookmarksToFolderRequest as Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AddBookmarksToFolderController
{
    public function __invoke(Request $request, RequestHandler $handler): JsonResponse
    {
        $handler->handle($request->getBookmarkIds(), $request->integer('folder'));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
