<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Rules\TwoFACodeRule;
use App\Services\VerifySecondaryEmailService as Service;
use App\ValueObjects\UserId;
use App\ValueObjects\TwoFACode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifySecondaryEmailController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'email'             => ['required', 'email'],
            'verification_code' => ['required', 'string', new TwoFACodeRule()]
        ]);

        $service->verify(
            UserId::fromAuthUser()->value(),
            $request->input('email'),
            TwoFACode::fromString($request->input('verification_code'))
        );

        return response()->json();
    }
}
