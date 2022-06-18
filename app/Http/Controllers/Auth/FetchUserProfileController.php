<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\ValueObjects\UserID;

final class FetchUserProfileController
{
    public function __invoke(UserRepository $repository): UserResource
    {
        return new UserResource($repository->findByID(UserID::fromAuthUser()));
    }
}
