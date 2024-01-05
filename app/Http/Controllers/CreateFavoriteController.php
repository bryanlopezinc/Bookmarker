<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\ResourceIdRule;
use App\Services\CreateFavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateFavoriteController
{
    public function __invoke(Request $request, CreateFavoriteService $service): JsonResponse
    {
        $maxBookmarks = 50;

        $request->validate([
            'bookmarks' => ['required', 'filled', join(':', ['max', $maxBookmarks])],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ], ['max' => "cannot add more than {$maxBookmarks} bookmarks simultaneously"]);

        $service->create($request->input('bookmarks'), (int)auth()->id());

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
