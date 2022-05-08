<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\ResourceIdRule;
use App\Services\DeleteUserFavouriteService;
use App\ValueObjects\ResourceId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteFavouriteController
{
    public function __invoke(Request $request, DeleteUserFavouriteService $service): JsonResponse
    {
        $request->validate([
            'bookmark' => ['required', new ResourceIdRule]
        ]);

        $service(ResourceId::fromRequest($request, 'bookmark'));

        return response()->json(status: 204);
    }
}
