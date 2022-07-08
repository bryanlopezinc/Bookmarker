<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\RequestPasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequestPasswordResetController
{
    public function __invoke(Request $request, RequestPasswordResetService $service): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $service->fromRequest($request);

        return response()->json(['message' => 'success']);
    }
}
