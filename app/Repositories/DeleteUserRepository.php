<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DeletedUser;
use App\Models\User;
use App\ValueObjects\UserID;
use Laravel\Passport\Passport;

final class DeleteUserRepository
{
    public function delete(UserID $userID): void
    {
        /** @var User */
        $user = User::query()->whereKey($userID->toInt())->first();

        $user->tokens()->chunkById(20, function (\Illuminate\Database\Eloquent\Collection $chunk) {
            $chunk->toQuery()->update(['revoked' => true]);
            Passport::refreshToken()->whereIn('access_token_id', $chunk->pluck('id')->all())->update(['revoked' => true]);
        });

        //Store the user ID which is the foreign key for all user resources
        //in a the 'deleted_users' table to used as reference to delete
        //all user records by scheduled tasks.
        DeletedUser::query()->create(['user_id' => $user->id]);

        $user->delete();
    }
}
