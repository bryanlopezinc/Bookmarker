<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidResourceIdException;

final class UserID
{
    public function __construct(protected readonly int $id)
    {
        $this->validate();
    }

    protected function validate(): void
    {
        throw_if(
            $this->id < 1,
            new InvalidResourceIdException("invalid " . class_basename($this) . ' ' . $this->id)
        );
    }

    public function value(): int
    {
        return $this->id;
    }

    public static function fromAuthUser(): self
    {
        return new self(auth('api')->id()); // @phpstan-ignore-line
    }

    public function equals(UserID $userId): bool
    {
        return $userId->id === $this->id;
    }
}
