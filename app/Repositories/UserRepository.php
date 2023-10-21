<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\UserNotFoundException;
use App\Models\SecondaryEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class UserRepository
{
    /**
     * @throws UserNotFoundException
     */
    public function findByUsername(string $username, array $columns = []): User
    {
        $result = $this->findMany('username', [$username], $columns);

        if ($result->isEmpty()) {
            throw new UserNotFoundException();
        }

        return $result->sole();
    }

    /**
     * @throws UserNotFoundException
     */
    public function findByID(int $userID, array $columns = []): User
    {
        $result = $this->findMany('id', [$userID], $columns);

        if ($result->isEmpty()) {
            throw new UserNotFoundException();
        }

        return $result->sole();
    }

    /**
     * @throws UserNotFoundException
     */
    public function findByEmail(string $email, array $columns = []): User
    {
        $result = $this->findMany('email', [$email], $columns);

        if ($result->isEmpty()) {
            throw new UserNotFoundException();
        }

        return $result->sole();
    }

    /**
     * @throws UserNotFoundException
     */
    public function findByEmailOrSecondaryEmail(string $email, array $columns = []): User
    {
        try {
            return User::WithQueryOptions($columns)
                ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
                ->where('users.email', $email)
                ->orWhere('users_emails.email', $email)
                ->sole();
        } catch (ModelNotFoundException) {
            throw new UserNotFoundException;
        }
    }

    /**
     * @return Collection<User>
     */
    private function findMany(string $byColumn, array $values, array $columns): Collection
    {
        return User::WithQueryOptions($columns)->whereIn($byColumn, $values)->get();
    }

    /**
     * @return array<string>
     */
    public function getUserSecondaryEmails(int $userID): array
    {
        return SecondaryEmail::query()
            ->where('user_id', $userID)
            ->get(['email'])
            ->map(fn (SecondaryEmail $secondaryEmail) => $secondaryEmail->email)
            ->all();
    }
}
