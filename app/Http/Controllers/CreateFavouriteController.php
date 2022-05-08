<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\ResourceIdRule;
use App\Services\CreateFavouriteService;
use App\ValueObjects\ResourceID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CreateFavouriteController
{
    public function __invoke(Request $request, CreateFavouriteService $service): JsonResponse
    {
        $request->validate([
            'bookmark' => ['required', new ResourceIdRule]
        ]);

        $service->create(ResourceID::fromRequest($request, 'bookmark'));

        return response()->json(status: Response::HTTP_CREATED);
    }
}
