<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RequestPasswordResetController
{
    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status  = $this->passwordBroker->sendResetLink($request->only('email'), function (User $user, string $token) {
            $user->notify(new ResetPasswordNotification($token));
        });

        $response = fn (string $message, int $status) => response()->json(['message' => $message], $status);

        return match ($status) {
            $this->passwordBroker::INVALID_USER => $response('Could not find user with given email', Response::HTTP_NOT_FOUND),
            $this->passwordBroker::RESET_THROTTLED => $response('Too many requests', Response::HTTP_TOO_MANY_REQUESTS),
            default => $response('success', Response::HTTP_OK)
        };
    }
}
