<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\ResendEmailVerificationLinkRequested;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResendVerificationLinkController
{
    public function __invoke(Request $request, UserRepository $repository): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = $repository->findByEmail(
            $request->input('email'),
            ['id', 'email', 'email_verified_at']
        );

        if ($user->email_verified_at) {
            return response()->json(['message' => 'EmailAlreadyVerified']);
        }

        event(new ResendEmailVerificationLinkRequested($user));

        return response()->json();
    }
}
