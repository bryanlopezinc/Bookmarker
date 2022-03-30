<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\AccesssTokenResource;
use App\Services\CreateUserService;
use Illuminate\Http\Response;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUserController extends AccessTokenController
{
    public function __invoke(CreateUserRequest $request, CreateUserService $service, ServerRequestInterface $serverRequest): AccesssTokenResource
    {
        return new AccesssTokenResource(
            $service->FromRequest($request),
            $this->issueToken($serverRequest)->content(),
            Response::HTTP_CREATED
        );
    }
}
