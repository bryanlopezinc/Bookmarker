<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ResetPasswordController
{
    public function __construct(private readonly PasswordBroker $passwordBroker, private readonly Hasher $hasher)
    {
    }

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $response = $this->passwordBroker->reset($request->only(['email', 'password', 'token']), function (User $user, string $password) {
            $user->password = $this->hasher->make($password);
            $user->save();
        });

        if ($response === PasswordBroker::INVALID_USER) {
            return response()->json(['message' => 'Could not find user with given email'], Response::HTTP_NOT_FOUND);
        }

        if ($response === PasswordBroker::INVALID_TOKEN) {
            return response()->json(['message' => 'Invalid reset token'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'success']);
    }
}
