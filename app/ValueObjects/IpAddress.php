<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final class IpAddress
{
    public function __construct(public readonly string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Invalid ip address ' . $value);
        }
    }
}
