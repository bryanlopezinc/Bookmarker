<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\ResourceIDsCollection;
use Illuminate\Http\Request;
use App\Rules\ResourceIdRule;
use Illuminate\Http\JsonResponse;
use App\Services\DeleteBookmarksService;

final class DeleteBookmarkController
{
    public function __invoke(Request $request, DeleteBookmarksService $service): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'max:100'],
            'ids.*' => [new ResourceIdRule, 'distinct:strict']
        ], ['max' => 'cannot delete more than 100 bookmarks in one request']);

        $service->delete(ResourceIDsCollection::fromNativeTypes($request->input('ids')));

        return response()->json();
    }
}
