<?php

declare(strict_types=1);

namespace App\TwoFA;

use App\ValueObjects\PositiveNumber;
use Illuminate\Support\Facades\Crypt;

final class VerificationCode
{
    public const LENGTH = 5;

    private int $value;

    public function __construct(int $value)
    {
        $this->value = $value;

        new PositiveNumber($value);
        
        $length = strlen((string)$value);

        if ($length !== self::LENGTH) {
            throw new InvalidVerificationCodeException(
                sprintf('Two factor code must be %s numbers but got %s', self::LENGTH, $length)
            );
        }
    }

    public function code(): int
    {
        return $this->value;
    }

    public static function fromString(string $code): self
    {
        return new self((int) $code);
    }

    public function equals(VerificationCode $code): bool
    {
        return $code->value === $this->value;
    }

    public function __serialize(): array
    {
        return [
            'value' => Crypt::encrypt($this->value)
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->value = Crypt::decrypt($data['value']);
    }
}
