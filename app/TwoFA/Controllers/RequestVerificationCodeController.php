<?php

declare(strict_types=1);

namespace App\TwoFA\Controllers;

use App\TwoFA\Requests\RequestVerificationCodeRequest;
use App\TwoFA\Services\RequestVerificationCodeService;

final class RequestVerificationCodeController
{
    public function __invoke(RequestVerificationCodeRequest $request, RequestVerificationCodeService $service)
    {
        $service($request);

        return response()->json(['message' => 'success']);
    }
}
