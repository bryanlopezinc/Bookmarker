<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\CreatedUserResource;
use App\Services\CreateUserService;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUserController extends AccessTokenController
{
    public function __invoke(CreateUserRequest $request, CreateUserService $service, ServerRequestInterface $serverRequest): CreatedUserResource
    {
        return new CreatedUserResource($service->FromRequest($request), $this->issueToken($serverRequest)->content());
    }
}
