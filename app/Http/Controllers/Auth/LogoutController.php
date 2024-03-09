<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \Laravel\Passport\Token */
        $token = User::fromRequest($request)->token();

        $token->revoke();

        return response()->json();
    }
}
