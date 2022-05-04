<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Models\User as UserModel;
use App\QueryColumns\UserQueryColumns;
use App\ValueObjects\Username;

final class UserRepository
{
    public function findByUsername(Username $username, UserQueryColumns $columns = new UserQueryColumns()): User|false
    {
        $user =  UserModel::WithQueryOptions($columns)->where('username', $username->value)->first();

        if (is_null($user)) {
            return false;
        }

        if (!$columns->has('id') && !$columns->isEmpty()) {
            $user->offsetUnset('id');
        }

        return UserBuilder::fromModel($user)->build();
    }
}
