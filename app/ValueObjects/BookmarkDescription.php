<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class BookmarkDescription
{
    public const MAX = 200;

    public function __construct(public readonly ?string $value)
    {
        if (blank($value)) return;

        if (mb_strlen($value) > self::MAX) {
            throw new \LengthException('Bookmark description cannot be greater ' . self::MAX);
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
