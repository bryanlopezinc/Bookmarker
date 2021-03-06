<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

final class BookmarkTitle
{
    public const MAX_LENGTH = 100;

    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    public static function rules(): array
    {
        return [
            'filled', 'string', 'max:' . self::MAX_LENGTH
        ];
    }

    public static function fromLongtText(string $text): self
    {
        try {
            $bookmarkTitle = new self($text);
            return $bookmarkTitle;
        } catch (\LengthException $e) {
            if ($e->getCode() === 5000) {
                throw $e;
            }

            return new self(
                //subtract 3 from MAX_LENGTH to accomodate the 'end' (...) value
                Str::limit($text, BookmarkTitle::MAX_LENGTH - 3)
            );
        }
    }

    private function validate(): void
    {
        //The bookmarks title can be the bookmark url
        // because the default title is the bookmark url
        //before it is updated or the bookmark page has no title tags in its raw html.
        if (Url::isValid($this->value)) {
            return;
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw new \LengthException('Bookmark title cannot be greater ' . self::MAX_LENGTH);
        }

        if (blank($this->value)) {
            throw new \LengthException('Bookmark title cannot be empty', 5000);
        }
    }

    /**
     * Get the sanitized bookmarkTitle.
     */
    public function safe(): string
    {
        return e($this->value);
    }
}
