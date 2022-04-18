<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RequestPasswordResetTokenController
{
    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email']
        ]);

        $tokenRepository = $this->passwordBroker->getRepository();

        $user = User::query()->where('email', $request->input('email'))->first();

        if ($user === null) {
            return response()->json(['message' => ' Could not find user with given email'], Response::HTTP_NOT_FOUND);
        }

        if ($tokenRepository->recentlyCreatedToken($user)) {
            return response()->json(['message' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return response()->json([
            'message' => 'success',
            'token' => $tokenRepository->create($user),
            'expires' => config('auth.passwords.users.expire')
        ]);
    }
}
