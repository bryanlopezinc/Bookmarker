<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Repositories\DeleteUserRepository;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;

final class DeleteUserService
{
    public function __construct(private DeleteUserRepository $repository, private Hasher $hasher)
    {
    }

    public function delete(Request $request): void
    {
        $passwordMatches = $this->hasher->check($request->input('password'), auth('api')->user()->getAuthPassword());

        if (!$passwordMatches) {
            throw HttpException::unAuthorized(['message' => 'Invalid password']);
        }

        $this->repository->delete(UserID::fromAuthUser());
    }
}
