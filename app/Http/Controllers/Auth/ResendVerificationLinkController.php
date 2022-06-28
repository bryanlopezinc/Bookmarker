<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\Builders\UserBuilder;
use App\Events\ResendEmailVerificationLinkRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResendVerificationLinkController
{
    public function __invoke(Request $request): JsonResponse
    {
        event(new ResendEmailVerificationLinkRequested(
            UserBuilder::fromModel($request->user('api'))->build(),
        ));

        return response()->json();
    }
}
