<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\ResourceIDsCollection as IDs;
use App\Http\Requests\AddBookmarksToFolderRequest;
use App\Services\AddBookmarksToFolderService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AddBookmarksToFolderController
{
    public function __invoke(AddBookmarksToFolderRequest $request, AddBookmarksToFolderService $service): JsonResponse
    {
        $service->add(
            IDs::fromNativeTypes($request->validated('bookmarks')),
            ResourceID::fromRequest($request, 'folder'),
            IDs::fromNativeTypes($request->validated('make_hidden', []))
        );

        return response()->json(status: Response::HTTP_CREATED);
    }
}
