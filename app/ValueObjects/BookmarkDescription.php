<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

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

    public static function limit(string $text): self
    {
        try {
            $bookmarkDescription = new self($text);
            return $bookmarkDescription;
        } catch (\LengthException) {
            return new self(
                //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
                Str::limit($text, self::MAX_LENGTH - 3)
            );
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
