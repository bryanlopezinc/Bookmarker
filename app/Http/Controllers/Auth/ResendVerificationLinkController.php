<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\Builders\UserBuilder;
use App\Events\ResendEmailVerificationLinkRequested;
use App\Http\Requests\ResendVerificationLinkRequest;
use App\ValueObjects\Url;
use Illuminate\Http\JsonResponse;

final class ResendVerificationLinkController
{
    public function __invoke(ResendVerificationLinkRequest $request): JsonResponse
    {
        event(new ResendEmailVerificationLinkRequested(
            UserBuilder::fromModel($request->user('api'))->build(),
            Url::tryFromString($request->input('verification_url'))
        ));

        return response()->json();
    }
}
