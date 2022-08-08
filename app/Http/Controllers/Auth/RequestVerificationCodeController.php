<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\RequestVerificationCodeRequest as Request;
use App\Services\RequestVerificationCodeService as Service;
use Illuminate\Http\JsonResponse;

final class RequestVerificationCodeController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service($request);

        return response()->json(['message' => 'success']);
    }
}
