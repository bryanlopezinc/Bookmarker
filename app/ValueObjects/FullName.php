<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

final class FullName
{
    public function __construct(public readonly string $value)
    {
        Assert::notEmpty($value);
    }

    public function present(): string
    {
        return Str::headline($this->value);
    }
}
