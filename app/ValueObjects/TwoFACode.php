<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Closure;
use Illuminate\Support\Facades\Crypt;
use LengthException;

final class TwoFACode
{
    public const LENGTH = 6;

    private static ?Closure $generator = null;

    public function __construct(private int $value)
    {
        if (!self::isValid($value)) {
            throw new LengthException("Invalid 2FA code $value");
        }
    }

    public static function isValid(string|int $code): bool
    {
        if (is_string($code)) {
            $code = intval($code);
        }

        return strlen(strval($code)) === self::LENGTH && $code > 0;
    }

    public static function generate(): self
    {
        $generator = static::$generator ??= function () {
            $randomInt = strval(random_int(0, 999_999));

            return intval(str_pad(
                $randomInt,
                self::LENGTH,
                strval(self::LENGTH),
                STR_PAD_LEFT
            ));
        };

        return new self($generator());
    }

    /**
     * Set the generator that will be used to generate new 2Fa codes.
     *
     * @param Closure():int $generator The closure should return a valid integer that can pass the TwoFACode validation.
     */
    public static function useGenerator(Closure $generator = null): void
    {
        static::$generator = $generator;
    }

    public static function fromString(string $code): self
    {
        return new self((int) $code);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value();
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
