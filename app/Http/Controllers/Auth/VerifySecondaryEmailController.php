<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Rules\VerificationCodeRule;
use App\Services\VerifySecondaryEmailService as Service;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\VerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifySecondaryEmailController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'verification_code' => ['required', new VerificationCodeRule]
        ]);

        $service->verify(
            UserID::fromAuthUser(),
            new Email($request->input('email')),
            new VerificationCode($request->input('verification_code'))
        );

        return response()->json();
    }
}
