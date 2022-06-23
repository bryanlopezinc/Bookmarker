<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\DeleteUserFavouritesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFavouriteController
{
    public function __invoke(Request $request, DeleteUserFavouritesService $service): JsonResponse
    {
        $request->validate([
            'bookmarks' => ['required', 'array', join(':', ['max', setting('MAX_DELETE_FAVOURITES')])],
            'bookmarks.*' => [new ResourceIdRule, 'distinct:strict'],
        ]);

        $service(ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')));

        return response()->json();
    }
}
