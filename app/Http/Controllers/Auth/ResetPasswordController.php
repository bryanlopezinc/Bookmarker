<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\HttpException;
use App\Exceptions\UserNotFoundException;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request, PasswordBroker $passwordBroker, Hasher $hasher): JsonResponse
    {
        $credentials = $request->only(['email', 'password', 'token']);

        $response = $passwordBroker->reset($credentials, function (User $user, string $password) use ($hasher) {
            $user->password = $hasher->make($password);
            $user->save();
        });

        if ($response === PasswordBroker::INVALID_USER) {
            throw new UserNotFoundException();
        }

        if ($response === PasswordBroker::INVALID_TOKEN) {
            throw new HttpException(['message' => 'InvalidResetToken'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return response()->json(['message' => 'success']);
    }
}
