<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Events\RegisteredEvent;
use App\Http\Requests\CreateUserRequest;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;

final class CreateUserController
{
    public function __invoke(CreateUserRequest $request, Hasher $hasher): JsonResponse
    {
        $user = User::query()->create([
            'username'   => $request->validated('username'),
            'first_name' => $request->validated('first_name'),
            'last_name'  => $request->validated('last_name'),
            'email'      => $request->validated('email'),
            'password'   => $hasher->make($request->validated('password'))
        ]);

        event(new RegisteredEvent($user));

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }
}
