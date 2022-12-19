<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\CreateFavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CreateFavoriteController
{
    public function __invoke(Request $request, CreateFavoriteService $service): JsonResponse
    {
        $maxBookmarks = setting('MAX_POST_FAVOURITES');

        $request->validate([
            'bookmarks' => ['required', 'filled', join(':', ['max', $maxBookmarks])],
            'bookmarks.*' => [new ResourceIdRule(), 'distinct:strict']
        ], ['max' => "cannot add more than {$maxBookmarks} bookmarks simultaneously"]);

        $service->create(ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
