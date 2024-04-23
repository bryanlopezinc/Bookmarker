<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

final class FetchUserProfileController
{
    public function __invoke(Request $request, UserRepository $repository): UserResource
    {
        $user = $repository->findByID(User::fromRequest($request)->id);

        return new UserResource($user);
    }
}
