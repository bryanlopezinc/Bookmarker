<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\User;
use App\Events\LoginEvent;
use App\Exceptions\InvalidUsernameException;
use App\Http\Requests\LoginUserRequest;
use App\Http\Resources\AccessTokenResource;
use App\IpGeoLocation\IpAddress;
use App\Repositories\UserRepository;
use App\ValueObjects\Email;
use App\ValueObjects\Username;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController extends AccessTokenController
{
    public function __invoke(
        ServerRequestInterface $serverRequest,
        UserRepository $repository,
        LoginUserRequest $request
    ): AccessTokenResource {
        $token = $this->issueToken($serverRequest)->content();

        $user = $this->getUser($request, $repository);

        event(new LoginEvent(
            $user,
            $request->input('with_agent', null),
            $request->has('with_ip') ? new IpAddress($request->input('with_ip')) : null
        ));

        return new AccessTokenResource($user, $token);
    }

    private function getUser(LoginUserRequest $request, UserRepository $repository): User
    {
        try {
            $user = $repository->findByUsername(Username::fromRequest($request));
        } catch (InvalidUsernameException) {
            $user = $repository->findByEmail(new Email($request->validated('username')));
        }

        /** @var User */
        return $user;
    }
}
