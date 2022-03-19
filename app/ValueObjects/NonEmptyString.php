<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class NonEmptyString
{
    public function __construct(public readonly string $value)
    {
        if (blank($value)) {
            throw new \InvalidArgumentException('Value cannot be empty');
        }
    }
}
