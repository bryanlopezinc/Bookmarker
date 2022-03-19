<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class WebPageDescription
{
    public function __construct(public readonly string $value)
    {
    }

    public function isEmpty(): bool
    {
        return blank($this->value);
    }
}
