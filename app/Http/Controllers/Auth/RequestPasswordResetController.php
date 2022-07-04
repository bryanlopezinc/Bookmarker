<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\UserNotFoundHttpException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->resolveResponse($status);
    }

    private function resolveResponse(string $status): JsonResponse
    {
        if ($status === PasswordBroker::INVALID_USER) {
            throw new UserNotFoundHttpException;
        }

        if ($status === PasswordBroker::RESET_THROTTLED) {
            throw new  ThrottleRequestsException('Too Many Requests');
        }

        return response()->json(['message' => 'success']);
    }
}
