<?php

declare(strict_types=1);

namespace App\Repositories\OAuth;

use App\Cache\User2FACodeRepository;
use App\Enums\TwoFaMode;
use App\Models\User;
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

        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $clientEntity
        );

        if ( ! $request->routeIs('loginUser') || ! $userEntity) {
            return $userEntity;
        };

        $userID = $userEntity->getIdentifier();

        /** @var User */
        $user = User::query()->find($userID, 'two_fa_mode');

        if ($user->two_fa_mode == TwoFaMode::NONE) {
            return $userEntity;
        }

        if ($request->missing('two_fa_code')) {
            throw new OAuthServerException('A verification code is required.', 6, '2FARequired');
        }

        if ( ! $this->user2FACodeRepository->has($userID)) {
            $this->throwException();
        };

        if ( ! $this->twoFACodeMatches(TwoFACode::fromString($request->input('two_fa_code')), $userID)) {
            $this->throwException();
        };

        $this->user2FACodeRepository->forget($userID);

        return $userEntity;
    }

    private function twoFACodeMatches(TwoFACode $code, int $userID): bool
    {
        return $this->user2FACodeRepository->get($userID)->equals($code);
    }

    private function throwException(): void
    {
        throw new OAuthServerException('The given verification code is invalid.', 6, 'invalidVerificationCode');
    }
}
