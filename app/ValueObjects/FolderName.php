<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;
use Stringable;
use Webmozart\Assert\Assert;

final class FolderName
{
    public function __construct(public readonly string $value)
    {
        Assert::notEmpty($value);
        Assert::maxLength($value, 50);
    }

    public function present(): string
    {
        return Str::headline($this->value);
    }
}
