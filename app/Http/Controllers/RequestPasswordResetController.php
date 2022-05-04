<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RequestPasswordResetRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RequestPasswordResetController
{
    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    public function __invoke(RequestPasswordResetRequest $request): JsonResponse
    {
        $status  = $this->passwordBroker->sendResetLink($request->only('email'), function (User $user, string $token) use ($request) {
            $user->notify(new ResetPasswordNotification($token, $request->validated('reset_url')));
        });

        $response = fn (string $message, int $status) => response()->json(['message' => $message], $status);

        return match ($status) {
            $this->passwordBroker::INVALID_USER => $response('Could not find user with given email', Response::HTTP_NOT_FOUND),
            $this->passwordBroker::RESET_THROTTLED => $response('Too many requests', Response::HTTP_TOO_MANY_REQUESTS),
            default => $response('success', Response::HTTP_OK)
        };
    }
}
