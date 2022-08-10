<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Facades\Validator;

final class Email
{
    public function __construct(public readonly string $value)
    {
        if (Validator::make(['value' => $value], ['value' => 'email'])->fails()) {
            throw new \DomainException('Invalid email ' . $value);
        }
    }

    public function equals(Email $email): bool
    {
        return $email->value === $this->value;
    }
}
