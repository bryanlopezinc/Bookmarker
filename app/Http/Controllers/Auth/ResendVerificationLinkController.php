<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\ResendEmailVerificationLinkRequested;
use Illuminate\Http\JsonResponse;

final class ResendVerificationLinkController
{
    public function __invoke(): JsonResponse
    {
        /** @var \App\Models\User */
        $user = auth()->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'EmailAlreadyVerified'], JsonResponse::HTTP_CONFLICT);
        }

        event(new ResendEmailVerificationLinkRequested($user));

        return response()->json();
    }
}
