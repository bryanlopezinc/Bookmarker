<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DeleteUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteUserAccountController
{
    public function __invoke(Request $request, DeleteUserService $service): JsonResponse
    {
        $request->validate([
            'password' => ['string', 'required', 'filled']
        ]);

        $service->delete($request);

        return response()->json();
    }
}
