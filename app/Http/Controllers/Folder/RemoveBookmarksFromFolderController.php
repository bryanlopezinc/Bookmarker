<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\RemoveBookmarksFromFolderService as Service;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RemoveBookmarksFromFolderController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'bookmarks' => ['required', 'array', 'max:30'],
            'bookmarks.*' => [new ResourceIdRule, 'distinct:strict'],
            'folder' => ['required', new ResourceIdRule]
        ]);

        $service->remove(
            ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')),
            ResourceID::fromRequest($request, 'folder')
        );

        return response()->json();
    }
}
