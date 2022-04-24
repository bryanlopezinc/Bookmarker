<?php

declare(strict_types=1);

namespace App\TwoFA;

final class VerificationCode
{
    public const LENGTH = 5;

    public function __construct(public readonly int $value)
    {
        $length = strlen((string)$value);

        if ($length !== self::LENGTH) {
            throw new InvalidVerificationCodeException(
                sprintf('Two factor code must be %s numbers but got %s', self::LENGTH, $length)
            );
        }
    }

    public static function fromString(string $code): self
    {
        return new self((int) $code);
    }

    public function equals(VerificationCode $code): bool
    {
        return $code->value === $this->value;
    }
}
