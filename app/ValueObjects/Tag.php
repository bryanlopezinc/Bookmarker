<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidTagException;
use Illuminate\Validation\Concerns\ValidatesAttributes;

final class Tag
{
    public const MAX_LENGTH = 22;

    private static array $checked = [];

    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    /**
     * @return array<string>
     */
    public static function rules(array $merge = []): array
    {
        return [
            'max:' . self::MAX_LENGTH, 'filled', 'alpha_num', ...$merge
        ];
    }

    private function validate(): void
    {
        if (isset(static::$checked[$this->value])) return;

        if (blank($this->value)) {
            throw new InvalidTagException('Tag cannot be empty', 998);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw new InvalidTagException('Tag length cannot be greater ' . self::MAX_LENGTH);
        }

        $validator = new class
        {
            use ValidatesAttributes;
        };

        if (!$validator->validateAlphaNum('', $this->value)) {
            throw new InvalidTagException('Tag can only contain alpha numeric characters');
        }

        static::$checked[$this->value] = true;
    }
}
