<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        //The auth middleware ensures a user always returned
         // @phpstan-ignore-next-line
        $request->user('api')->token()->revoke();

        return response()->json();
    }
}
