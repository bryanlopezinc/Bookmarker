<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\ResetPasswordRequest;
use App\Services\ResetPasswordService;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordService $service): JsonResponse
    {
        $service($request);

        return response()->json(['message' => 'success']);
    }
}
