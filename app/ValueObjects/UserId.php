<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class UserID extends ResourceID
{
    public static function fromAuthUser(): self
    {
        return new self(auth('api')->id()); // @phpstan-ignore-line
    }

    public function equals(UserID $userId): bool
    {
        return $userId->id === $this->id;
    }
}
