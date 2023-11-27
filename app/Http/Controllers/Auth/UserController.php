<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\TwoFaMode;
use App\Events\RegisteredEvent;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;

final class UserController
{
    public function __invoke(CreateUserRequest $request, Hasher $hasher): JsonResponse
    {
        $user = User::query()->create([
            'username'    => $request->validated('username'),
            'first_name'  => $request->validated('first_name'),
            'last_name'   => $request->validated('last_name'),
            'email'       => $request->validated('email'),
            'password'    => $hasher->make($request->validated('password')),
            'two_fa_mode' => TwoFaMode::NONE
        ]);

        event(new RegisteredEvent($user));

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateProfileRequest $request, Hasher $hasher): JsonResponse
    {
        /** @var User */
        $user = auth()->user();

        $validated = $request->safe();

        $attributes = $validated->only(['first_name', 'last_name']);

        if ($validated->has('password')) {
            $attributes['password'] = $hasher->make($validated->offsetGet('password'));
        }

        if ($validated->has('two_fa_mode')) {
            $attributes['two_fa_mode'] = TwoFaMode::fromRequest($request);
        }

        $user->update($attributes);

        return new JsonResponse;
    }
}
