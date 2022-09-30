<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Request2FACodeRequest as Request;
use App\Services\Request2FACodeService as Service;
use Illuminate\Http\JsonResponse;

final class Request2FACodeController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service($request);

        return response()->json(['message' => 'success']);
    }
}
