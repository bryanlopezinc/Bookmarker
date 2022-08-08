<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Cache\VerificationCodesRepository;
use App\ValueObjects\UserID;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use App\ValueObjects\VerificationCode;

final class VerifyVerificationCode implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly VerificationCodesRepository $verificationCodes
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity)
    {
        $request = request();

        $user = $this->userRepository->getUserEntityByUserCredentials($username, $password, $grantType, $clientEntity);

        if (!$request->routeIs('loginUser') || !$user) {
            return $user;
        };

        $userID = new UserID($user->getIdentifier());

        if (!$this->verificationCodes->has($userID)) {
            $this->throwException();
        };

        if (!$this->verificationCodeMatches(VerificationCode::fromString($request->input('two_fa_code')), $userID)) {
            $this->throwException();
        };

        $this->verificationCodes->forget($userID);

        return $user;
    }

    private function verificationCodeMatches(VerificationCode $code, UserID $userID): bool
    {
        return $this->verificationCodes->get($userID)->equals($code);
    }

    private function throwException(): void
    {
        throw new OAuthServerException('The given verification code is invalid.', 6, 'invalidVerificationCode');
    }
}
