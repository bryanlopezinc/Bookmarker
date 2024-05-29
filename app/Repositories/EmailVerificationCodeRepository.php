<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\SecondaryEmailVerificationData;
use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Cache\Repository;

final class EmailVerificationCodeRepository
{
    public function __construct(
        private readonly Repository $store,
        private readonly int $ttl
    ) {
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function put(int $userID, string $email, TwoFACode $code): void
    {
        $record = [$email, $code];

        $this->store->put($this->key($userID), $record, $this->ttl);
    }

    public function has(int $userID): bool
    {
        return ! empty($this->getRecord($userID));
    }

    public function get(int $userID): SecondaryEmailVerificationData
    {
        $record = $this->getRecord($userID);

        return new SecondaryEmailVerificationData($record[0], $record[1]);
    }

    private function getRecord(int $userID): array
    {
        return $this->store->get($this->key($userID), []);
    }

    private function key(int $userID): string
    {
        return 'se_vc:' . $userID;
    }
}
