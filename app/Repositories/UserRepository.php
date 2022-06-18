<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Models\User as UserModel;
use App\QueryColumns\UserAttributes;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\Username;

final class UserRepository
{
    public function findByUsername(Username $username, UserAttributes $columns = new UserAttributes()): User|false
    {
        return $this->find('users.username', $username->value, $columns);
    }

    public function findByID(UserID $userID, UserAttributes $columns = new UserAttributes()): User|false
    {
        return $this->find('users.id', $userID->toInt(), $columns);
    }

    public function findByEmail(Email $email, UserAttributes $columns = new UserAttributes()): User|false
    {
        return $this->find('users.email', $email->value, $columns);
    }

    private function find(string $byColumn, string|int $value, UserAttributes $columns): User|false
    {
        $user =  UserModel::WithQueryOptions($columns)->where($byColumn, $value)->first();

        if (is_null($user)) {
            return false;
        }

        if (!$columns->has('id') && !$columns->isEmpty()) {
            $user->offsetUnset('id');
        }

        return UserBuilder::fromModel($user)->build();
    }
}
