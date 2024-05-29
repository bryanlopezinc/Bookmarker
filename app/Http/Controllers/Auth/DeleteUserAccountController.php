<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DeleteUserAccountController
{
    public function __invoke(Request $request, Hasher $hasher): JsonResponse
    {
        $authUser = User::fromRequest($request);

        $request->validate([
            'password' => ['string', 'required', 'filled']
        ]);

        $passwordMatches = $hasher->check($request->input('password'), $authUser->getAuthPassword());

        if ( ! $passwordMatches) {
            throw ValidationException::withMessages(['password' => 'InvalidPassword']);
        }

        $authUser->delete();

        return response()->json();
    }
}
