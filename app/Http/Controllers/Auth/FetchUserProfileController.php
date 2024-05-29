<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

final class FetchUserProfileController
{
    public function __invoke(Request $request): UserResource
    {
        $user = User::fromRequest($request)->loadCount(['bookmarks', 'folders', 'favorites']);

        return new UserResource($user);
    }
}
