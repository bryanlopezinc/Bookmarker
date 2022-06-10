<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\AddBookmarksToFolderService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AddBookmarksToFolderController
{
    public function __invoke(Request $request, AddBookmarksToFolderService $service): JsonResponse
    {
        $request->validate([
            'bookmarks' => ['required', 'array', 'max:30'],
            'bookmarks.*' => [new ResourceIdRule],
            'folder' => ['required', new ResourceIdRule]
        ]);

        $service->add(
            ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')),
            ResourceID::fromRequest($request, 'folder')
        );

        return response()->json(status: Response::HTTP_CREATED);
    }
}
