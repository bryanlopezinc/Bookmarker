<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Events\LoginEvent;
use App\Exceptions\InvalidUsernameException;
use App\Http\Requests\LoginUserRequest;
use App\Http\Resources\AccessTokenResource;
use App\ValueObjects\IpAddress;
use App\ValueObjects\Username;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController extends AccessTokenController
{
    public function __invoke(
        ServerRequestInterface $serverRequest,
        LoginUserRequest $request
    ): AccessTokenResource {
        $token = $this->issueToken($serverRequest)->content();

        $user = $this->getUser($request);

        event(new LoginEvent(
            $user,
            $request->input('with_agent', null),
            $request->has('with_ip') ? new IpAddress($request->input('with_ip')) : null
        ));

        return new AccessTokenResource($user, $token);
    }

    private function getUser(LoginUserRequest $request): User
    {
        $query = User::query()->withCount(['bookmarks', 'favorites', 'folders']);

        try {
            $username = new Username($request->validated('username'));

            return $query->where('username', $username->value)->firstOrNew();
        } catch (InvalidUsernameException) {
            return $query->where('email', $request->validated('username'))->firstOrNew();
        }
    }
}
