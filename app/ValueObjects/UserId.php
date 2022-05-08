<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class UserId extends ResourceID
{
    public static function fromAuthUser(): self
    {
        return new self(auth('api')->id());
    }

    public function equals(UserId $userId): bool
    {
        return $userId->id === $this->id;
    }
}
