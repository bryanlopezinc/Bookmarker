<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;

final class VerifyEmailController
{
    public function __invoke(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return response()->json();
    }
}
