<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserNotFoundHttpException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

final class RequestPasswordResetService
{
    public function __construct(private readonly PasswordBroker $passwordBroker)
    {
    }

    public function fromRequest(Request $request): void
    {
        $status  = $this->passwordBroker->sendResetLink($request->only('email'), function (User $user, string $token) {
            $user->notify(new ResetPasswordNotification($token));
        });

        if ($status === PasswordBroker::INVALID_USER) {
            throw new UserNotFoundHttpException();
        }

        if ($status === PasswordBroker::RESET_THROTTLED) {
            throw new  ThrottleRequestsException('Too Many Requests');
        }
    }
}
