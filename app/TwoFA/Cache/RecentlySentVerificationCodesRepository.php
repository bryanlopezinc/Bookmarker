<?php

declare(strict_types=1);

namespace App\TwoFA\Cache;

use App\ValueObjects\UserId;
use Illuminate\Contracts\Cache\Repository;

final class RecentlySentVerificationCodesRepository
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function put(UserId $userId, \DateTimeInterface|\DateInterval|int  $ttl): bool
    {
        return $this->repository->put($this->parseKey($userId), true, $ttl);
    }

    public function has(UserId $userId): bool
    {
        return $this->repository->has($this->parseKey($userId));
    }

    private function parseKey(UserId $userId): string
    {
        return 'sent2fa::' . $userId->toInt();
    }
}
