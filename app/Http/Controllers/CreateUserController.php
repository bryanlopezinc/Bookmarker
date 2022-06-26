<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\RegisteredEvent;
use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\AccesssTokenResource;
use App\Services\CreateUserService;
use App\ValueObjects\Url;
use Illuminate\Http\Response;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUserController extends AccessTokenController
{
    public function __invoke(CreateUserRequest $request, CreateUserService $service, ServerRequestInterface $serverRequest): AccesssTokenResource
    {
        $user = $service->FromRequest($request);

        $accessToken = $this->issueToken($serverRequest)->content();

        event(new RegisteredEvent($user, new Url($request->input('verification_url'))));

        return new AccesssTokenResource($user, $accessToken, Response::HTTP_CREATED);
    }
}
