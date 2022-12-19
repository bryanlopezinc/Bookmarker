<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\SecondaryEmail;
use App\Models\User;
use App\Services\AddEmailToAccountService as Service;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AddEmailToAccountController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'email' => [
                'bail',
                'required',
                'email',
                Rule::unique(User::class, 'email'),
                Rule::unique(SecondaryEmail::class, 'email')
            ]
        ]);

        $service(UserID::fromAuthUser(), new Email($request->input('email')));

        return response()->json();
    }
}
