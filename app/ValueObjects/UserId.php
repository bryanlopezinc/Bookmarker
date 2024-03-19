<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidResourceIdException;

final class UserId
{
    public function __construct(protected readonly int $id)
    {
        $this->validate();
    }

    private function validate(): void
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
        /** @var int */
        $id = auth()->id();

        return new self($id);
    }
}
