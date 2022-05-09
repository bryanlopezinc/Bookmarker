<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Collections\ResourceIDsCollection;
use App\Rules\ResourceIdRule;
use App\Services\CreateFavouriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CreateFavouriteController
{
    public function __invoke(Request $request, CreateFavouriteService $service): JsonResponse
    {
        $request->validate([
            'bookmarks' => ['required', 'filled', 'max:30'],
            'bookmarks.*' => [new ResourceIdRule]
        ], ['max' => 'cannot add more than 30 bookmarks simultaneously']);

        $service->create(ResourceIDsCollection::fromNativeTypes($request->input('bookmarks')));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
