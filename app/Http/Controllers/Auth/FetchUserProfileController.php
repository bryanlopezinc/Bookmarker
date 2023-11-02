<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\ValueObjects\UserId;

final class FetchUserProfileController
{
    public function __invoke(UserRepository $repository): UserResource
    {
        /** @var User */
        $user = $repository->findByID(UserId::fromAuthUser()->value());

        return new UserResource($user);
    }
}
