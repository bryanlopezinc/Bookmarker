<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\ResendEmailVerificationLinkRequested;
use App\QueryColumns\UserAttributes;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ResendVerificationLinkController
{
    public function __invoke(Request $request, UserRepository $repository): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = $repository->findByEmail(
            new Email($request->input('email')),
            UserAttributes::only('id,email,email_verified_at')
        );

        if ($user === false) {
            return response()->json(status: Response::HTTP_NOT_FOUND);
        }

        if ($user->hasVerifiedEmail) {
            return response()->json([
                'message' => 'Email already verified'
            ]);
        }

        event(new ResendEmailVerificationLinkRequested($user));

        return response()->json();
    }
}
