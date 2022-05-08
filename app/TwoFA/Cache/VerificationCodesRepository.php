<?php

declare(strict_types=1);

namespace App\TwoFA\Cache;

use App\TwoFA\TwoFactorData;
use App\ValueObjects\UserID;
use Illuminate\Contracts\Cache\Repository;

final class VerificationCodesRepository
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function put(TwoFactorData $data, \DateTimeInterface|\DateInterval|int  $expireAfter): bool
    {
        return  $this->repository->put($this->parseKey($data->userID), $data, $expireAfter);
    }

    public function has(UserID $userID): bool
    {
        return $this->repository->has($this->parseKey($userID));
    }

    public function get(UserID $userID): TwoFactorData
    {
        return $this->repository->get($this->parseKey($userID));
    }

    private function parseKey(UserID $userID): string
    {
        return 'Users::2fa::' . $userID->toInt();
    }
}
