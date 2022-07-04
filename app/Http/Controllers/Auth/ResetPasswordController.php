<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundHttpException;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ResetPasswordController
{
    public function __construct(
        private readonly PasswordBroker $passwordBroker,
        private readonly Hasher $hasher
    ) {
    }

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password', 'token']);

        $response = $this->passwordBroker->reset($credentials, function (User $user, string $password) {
            $user->password = $this->hasher->make($password);
            $user->save();
        });

        if ($response === PasswordBroker::INVALID_USER) {
            throw new UserNotFoundHttpException;
        }

        if ($response === PasswordBroker::INVALID_TOKEN) {
            throw new HttpException(['message' => 'Invalid reset token'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(['message' => 'success']);
    }
}
