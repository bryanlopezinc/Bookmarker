<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Models\User as Model;

final class CreateUserRepository
{
    /**
     * @throws \RuntimeException throws exception if user password is not hashed
     */
    public function create(User $user): User
    {
        if (password_get_info($user->password)['algoName'] === 'unknown') {
            throw new \RuntimeException('User password must be hashed');
        }

        $user = Model::query()->create([
            'username' => $user->username->value,
            'firstname' => $user->firstName->value,
            'lastname' => $user->lastName->value,
            'email'    => $user->email->value,
            'password' => $user->password
        ]);

        return UserBuilder::fromModel($user)
            ->bookmarksCount(0)
            ->favoritesCount(0)
            ->foldersCount(0)
            ->emailVerifiedAt(null)
            ->build();
    }
}
