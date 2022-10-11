<?php

declare(strict_types=1);

namespace App\Cache;

use App\ValueObjects\TwoFACode;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Cache\Repository;

final class User2FACodeRepository
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function put(UserID $userID, TwoFACode $code, \DateTimeInterface|\DateInterval|int  $expireAfter): bool
    {
        return  $this->repository->put($this->key($userID), $code, $expireAfter);
    }

    public function has(UserID $userID): bool
    {
        return $this->repository->has($this->key($userID));
    }

    public function get(UserID $userID): TwoFACode
    {
        return $this->repository->get($this->key($userID));
    }

    public function forget(UserID $userID): void
    {
        $this->repository->forget($this->key($userID));
    }

    private function key(UserID $userID): string
    {
        return 'Users::2fa::' . $userID->toInt();
    }
}