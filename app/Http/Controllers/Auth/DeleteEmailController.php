<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\RemoveEmailService;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteEmailController
{
    public function __invoke(Request $request, RemoveEmailService $service): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $service->delete(UserID::fromAuthUser(), new Email($request->input('email')));

        return response()->json();
    }
}
