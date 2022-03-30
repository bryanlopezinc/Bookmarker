<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AccesssTokenResource;
use App\Repositories\UserRepository;
use App\ValueObjects\Username;
use Illuminate\Http\Response;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController extends AccessTokenController
{
    public function __invoke(ServerRequestInterface $request, UserRepository $repository): AccesssTokenResource
    {
        $token = $this->issueToken($request)->content();

        $user = $repository->findByUsername(new Username($request->getParsedBody()['username']));

        return new AccesssTokenResource($user, $token, Response::HTTP_OK);
    }
}
