<?php

declare(strict_types=1);

namespace App\Repositories\OAuth;

use App\Models\User;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

final class EnsureEmailHasBeenVerified implements UserRepositoryInterface
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
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
        $userEntity = $this->userRepository->getUserEntityByUserCredentials(
            $username,
            $password,
            $grantType,
            $clientEntity
        );

        if ( ! $userEntity) {
            return $userEntity;
        };

        /** @var User */
        $user = User::query()->find($userEntity->getIdentifier(), ['email_verified_at']);

        if ($user->email_verified_at === null) {
            throw new OAuthServerException(
                'The user email has not been verified.',
                7,
                'userEmailNotVerified'
            );
        }

        return $userEntity;
    }
}
