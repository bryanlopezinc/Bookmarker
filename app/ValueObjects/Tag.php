<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidTagException;

final class Tag
{
    public const MAX_LENGTH = 22;

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
            'max:' . self::MAX_LENGTH, 'filled',  ...$merge
        ];
    }

    private function validate(): void
    {
        if (blank($this->value)) {
            throw new InvalidTagException('Tag cannot be empty', 998);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw new InvalidTagException('Tag length cannot be greater ' . self::MAX_LENGTH);
        }
    }
}
