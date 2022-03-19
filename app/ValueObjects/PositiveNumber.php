<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class PositiveNumber
{
    public function __construct(public readonly int $value)
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(
                sprintf('Number must be greater or equal to zero %s given', $value)
            );
        }
    }
}
