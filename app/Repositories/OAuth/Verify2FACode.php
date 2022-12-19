<?php

declare(strict_types=1);

namespace App\Repositories\OAuth;

use App\Cache\User2FACodeRepository;
use App\ValueObjects\UserID;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use App\ValueObjects\TwoFACode;

final class Verify2FACode implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly User2FACodeRepository $user2FACodeRepository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        $request = request();

        $user = $this->userRepository->getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity);

        if (!$request->routeIs('loginUser') || !$user) {
            return $user;
        };

        $userID = new UserID($user->getIdentifier());

        if (!$this->user2FACodeRepository->has($userID)) {
            $this->throwException();
        };

        if (!$this->twoFACodeMatches(TwoFACode::fromString($request->input('two_fa_code')), $userID)) {
            $this->throwException();
        };

        $this->user2FACodeRepository->forget($userID);

        return $user;
    }

    private function twoFACodeMatches(TwoFACode $code, UserID $userID): bool
    {
        return $this->user2FACodeRepository->get($userID)->equals($code);
    }

    private function throwException(): void
    {
        throw new OAuthServerException('The given verification code is invalid.', 6, 'invalidVerificationCode');
    }
}
