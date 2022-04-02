<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\LoginEvent;
use App\Http\Resources\AccesssTokenResource;
use App\IpGeoLocation\IpAddress;
use App\Repositories\UserRepository;
use App\ValueObjects\Username;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController extends AccessTokenController
{
    public function __invoke(ServerRequestInterface $serverRequest, UserRepository $repository, Request $request): AccesssTokenResource
    {
        $request->validate([
            'with_ip' => ['ip', 'sometimes', 'filled'],
            'with_agent' => ['sometimes', 'filled']
        ]);

        $token = $this->issueToken($serverRequest)->content();

        $user = $repository->findByUsername(new Username($request->input('username')));

        event(new LoginEvent(
            $user,
            $request->input('with_agent', null),
            $request->has('with_ip') ? new IpAddress($request->input('with_ip',)) : null
        ));

        return new AccesssTokenResource($user, $token, Response::HTTP_OK);
    }
}
