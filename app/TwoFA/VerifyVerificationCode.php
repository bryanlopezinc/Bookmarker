<?php

declare(strict_types=1);

namespace App\TwoFA;

use App\TwoFA\Cache\VerificationCodesRepository;
use App\ValueObjects\UserID;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

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

        if (!$this->verificationCodes->get($userID)->verificationCode->equals(VerificationCode::fromString($request->input('two_fa_code')))) {
            $this->throwException();
        };

        return $user;
    }

    private function throwException(): void
    {
        throw new OAuthServerException('The given verification code is invalid.', 6, 'invalidVerificationCode');
    }
}
