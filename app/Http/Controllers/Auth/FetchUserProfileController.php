<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\User;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\ValueObjects\UserID;

final class FetchUserProfileController
{
    public function __invoke(UserRepository $repository): UserResource
    {
        /** @var User */
        $user = $repository->findByID(UserID::fromAuthUser());

        return new UserResource($user);
    }
}
