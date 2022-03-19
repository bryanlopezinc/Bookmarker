<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\Builders\UserBuilder;
use App\DataTransferObjects\User;
use App\Http\Requests\CreateUserRequest;
use App\Repositories\CreateUserRepository;
use Illuminate\Contracts\Hashing\Hasher;

final class CreateUserService
{
    public function __construct(private CreateUserRepository $repository, private Hasher $hasher)
    {
    }

    public function FromRequest(CreateUserRequest $request): User
    { 
        $user = UserBuilder::new()
            ->email($request->validated('email'))
            ->password($this->hasher->make($request->validated('password')))
            ->firstname($request->validated('firstname'))
            ->lastname($request->validated('lastname'))
            ->username($request->validated('username'))
            ->build();

        return $this->repository->create($user);
    }
}
