<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\SecondaryEmailAlreadyVerifiedException;
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

        try {
            $service->verify(
                UserId::fromAuthUser()->value(),
                $request->input('email'),
                TwoFACode::fromString($request->input('verification_code'))
            );
        } catch (SecondaryEmailAlreadyVerifiedException) {
            return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
        }

        return new JsonResponse();
    }
}
