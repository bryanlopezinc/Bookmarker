<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

final class Uuid
{
    public function __construct(public readonly string $value)
    {
        if (!Str::isUuid($value)) {
            throw new \InvalidArgumentException("The given uuid [$value] is invalid");
        }
    }

    public static function generate(): self
    {
        return new self(Str::uuid()->toString());
    }
}
