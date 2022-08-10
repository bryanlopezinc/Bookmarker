<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\AddEmailToAccountService as Service;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AddEmailToAccountController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $service(UserID::fromAuthUser(), new Email($request->input('email')));

        return response()->json();
    }
}
