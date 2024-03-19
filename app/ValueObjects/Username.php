<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidUsernameException;

final class Username
{
    public const MAX_LENGTH = 15;
    public const MIN_LENGTH = 8;
    public const REGEX = '/^[A-Za-z0-9_]+$/';

    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    /**
     * @return array<string>
     */
    public static function rules(array $merge = []): array
    {
        return array_merge([
            'string',
            'min:' . self::MIN_LENGTH,
            'max:' . self::MAX_LENGTH,
            'regex:' . self::REGEX
        ], $merge);
    }

    private function validate(): void
    {
        $length = mb_strlen($this->value);

        if ($length > self::MAX_LENGTH) {
            throw InvalidUsernameException::lengthExceeded();
        }

        if ($length < self::MIN_LENGTH) {
            throw InvalidUsernameException::nameTooShort();
        }

        if ( ! preg_match(self::REGEX, $this->value)) {
            throw InvalidUsernameException::invalidCharacters();
        }
    }
}
