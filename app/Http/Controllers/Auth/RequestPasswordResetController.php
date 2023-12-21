<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

final class RequestPasswordResetController
{
    public function __invoke(Request $request, PasswordBroker $passwordBroker): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = $passwordBroker->sendResetLink($request->only('email'), function (User $user, string $token) {
            $user->notify(new ResetPasswordNotification($token));
        });

        if ($status === PasswordBroker::INVALID_USER) {
            throw new UserNotFoundException();
        }

        if ($status === PasswordBroker::RESET_THROTTLED) {
            throw new ThrottleRequestsException('TooManyPasswordResetRequests');
        }

        return response()->json(['message' => 'success']);
    }
}
