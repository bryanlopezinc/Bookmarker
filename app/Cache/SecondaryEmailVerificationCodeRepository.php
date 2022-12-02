<?php

declare(strict_types=1);

namespace App\Cache;

use App\ValueObjects\Email;
use App\ValueObjects\UserID;
use App\ValueObjects\TwoFACode;
use Illuminate\Contracts\Cache\Repository;

final class SecondaryEmailVerificationCodeRepository
{
    public function __construct(private readonly Repository $store, private readonly int $ttl)
    {
    }

    public function put(UserID $userID, Email $email, TwoFACode $code): void
    {
        $record = [];

        $record[] = $email;
        $record[] = $code;

        $this->store->put($this->key($userID), $record, $this->ttl);
    }

    public function has(UserID $userID): bool
    {
        return !empty($this->getRecord($userID));
    }

    public function get(UserID $userID): SecondaryEmailVerificationData
    {
        $record = $this->getRecord($userID);

        return new SecondaryEmailVerificationData($record[0], $record[1]);
    }

    public function forget(UserID $userID): void
    {
        $this->store->delete($this->key($userID));
    }

    private function getRecord(UserID $userID): array
    {
        return $this->store->get($this->key($userID), []);
    }

    private function key(UserID $userID): string
    {
        return 'se_vc:' . $userID->value();
    }
}
