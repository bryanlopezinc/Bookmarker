<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Models\User as UserModel;
use App\ValueObjects\Username;
use Illuminate\Support\Facades\DB;

final class UserRepository
{
    public function findByUsername(Username $username): User|false
    {
        $sql = <<<SQL
                CASE
                    WHEN user_bookmarks_count.count IS NULL THEN 0
                    ELSE user_bookmarks_count.count
                END as 'bookmarks_count'
             SQL;

        $user =  UserModel::query()
            ->select(['users.id', 'username', 'firstname', 'lastname', DB::raw($sql)])
            ->join('user_bookmarks_count', 'users.id', '=', 'user_bookmarks_count.user_id', 'left outer')
            ->where('username', $username->value)
            ->first();

        return $user === null ? false : UserBuilder::fromModel($user)->build();
    }
}
