<?php

declare(strict_types=1);

namespace App\Cache;

use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Cache\Repository;

final class User2FACodeRepository
{
    public function __construct(private readonly Repository $repository, private readonly int $ttl)
    {
    }

    public function put(int $userID, TwoFACode $code): bool
    {
        return  $this->repository->put($this->key($userID), $code, $this->ttl);
    }

    public function has(int $userID): bool
    {
        return $this->repository->has($this->key($userID));
    }

    public function get(int $userID): TwoFACode
    {
        return $this->repository->get($this->key($userID));
    }

    public function forget(int $userID): void
    {
        $this->repository->forget($this->key($userID));
    }

    private function key(int $userID): string
    {
        return 'Users::2fa::' . $userID;
    }
}
