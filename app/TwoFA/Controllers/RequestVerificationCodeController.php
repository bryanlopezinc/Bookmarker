<?php

declare(strict_types=1);

namespace App\TwoFA\Controllers;

use App\TwoFA\Requests\RequestVerificationCodeRequest;
use App\TwoFA\Services\RequestVerificationCodeService;
use Illuminate\Http\JsonResponse;

final class RequestVerificationCodeController
{
    public function __invoke(RequestVerificationCodeRequest $request, RequestVerificationCodeService $service): JsonResponse
    {
        $service($request);

        return response()->json(['message' => 'success']);
    }
}
