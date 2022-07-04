<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class BookmarkDescription
{
    public const MAX_LENGTH = 200;

    public function __construct(public readonly ?string $value)
    {
        if (blank($value)) return;

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new \LengthException('Bookmark description cannot be greater ' . self::MAX_LENGTH);
        }
    }

    public function isEmpty(): bool
    {
        return blank($this->value);
    }

    /**
     * Get the sanitized bookmarkDescription.
     */
    public function safe(): string
    {
        return e($this->value);
    }
}
