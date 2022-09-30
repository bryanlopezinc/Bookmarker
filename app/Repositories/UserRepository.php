<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Models\SecondaryEmail;
use App\Models\User as UserModel;
use App\QueryColumns\UserAttributes;
use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\Username;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    public function findByEmailOrSecondaryEmail(Email $email, UserAttributes $columns = new UserAttributes()): User|false
    {
        try {
            return UserBuilder::fromModel(
                UserModel::WithQueryOptions($columns)
                    ->leftJoin('users_emails', 'users.id', '=', 'users_emails.user_id')
                    ->where('users.email', $email->value)
                    ->orWhere('users_emails.email', $email->value)
                    ->sole()
            )->build();
        } catch (ModelNotFoundException) {
            return false;
        }
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

    /**
     * @return array<Email>
     */
    public function getUserSecondaryEmails(UserID $userID): array
    {
        return SecondaryEmail::query()
            ->where('user_id', $userID->toInt())
            ->get(['email'])
            ->map(fn (SecondaryEmail $secondaryEmail) => new Email($secondaryEmail->email))
            ->all();
    }

    public function secondaryEmailExists(Email $secondaryEmail): bool
    {
        return SecondaryEmail::where('email', $secondaryEmail->value)->exists();
    }

    public function addSecondaryEmail(Email $secondaryEmail, UserID $userID): void
    {
        SecondaryEmail::create([
            'email' => $secondaryEmail->value,
            'user_id' => $userID->toInt(),
            'verified_at' => now()
        ]);
    }

    public function deleteSecondaryEmail(Email $email): bool
    {
        return (bool) SecondaryEmail::query()->where('email', $email->value)->delete();
    }
}
