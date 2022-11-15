<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\Invalid2FACodeException;
use Closure;
use Illuminate\Support\Facades\Crypt;

final class TwoFACode
{
    public const LENGTH = 5;

    private static ?Closure $generator = null;
    private int $value;

    public function __construct(int $value)
    {
        $length = strlen((string)$value);
        $this->value = $value;

        if ($length !== self::LENGTH || $value < 0) {
            throw new Invalid2FACodeException('Invalid 2FA code ' . $value);
        }
    }

    public static function generate(): self
    {
        $generator = static::$generator ??= function () {
            return random_int(10_000, 99_999);
        };

        return new self($generator());
    }

    /**
     * Set the generator that will be used to generate new 2Fa codes.
     *
     * @param Closure $generator The closure should return a valid integer that can pass the TwoFACode validation.
     */
    public static function useGenerator(Closure $generator = null): void
    {
        static::$generator = $generator;
    }

    public static function fromString(string $code): self
    {
        return new self((int) $code);
    }

    public function code(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->code();
    }

    public function equals(TwoFACode $code): bool
    {
        return $code->value === $this->value;
    }

    public function __serialize(): array
    {
        return ['value' => Crypt::encrypt($this->value)];
    }

    public function __unserialize(array $data): void
    {
        $this->value = Crypt::decrypt($data['value']);
    }
}
