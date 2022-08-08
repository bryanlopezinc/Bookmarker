<?php

declare(strict_types=1);

namespace App\Cache;

use App\ValueObjects\VerificationCode;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Cache\Repository;

final class VerificationCodesRepository
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function put(UserID $userID, VerificationCode $code, \DateTimeInterface|\DateInterval|int  $expireAfter): bool
    {
        return  $this->repository->put($this->parseKey($userID), $code, $expireAfter);
    }

    public function has(UserID $userID): bool
    {
        return $this->repository->has($this->parseKey($userID));
    }

    public function get(UserID $userID): VerificationCode
    {
        return $this->repository->get($this->parseKey($userID));
    }

    public function forget(UserID $userID): void
    {
        $this->repository->forget($this->parseKey($userID));
    }

    private function parseKey(UserID $userID): string
    {
        return 'Users::2fa::' . $userID->toInt();
    }
}
