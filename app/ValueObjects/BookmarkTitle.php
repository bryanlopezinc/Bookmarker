<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class BookmarkTitle
{
    public const MAX = 100;

    public function __construct(public readonly string $value)
    {
        $this->validate();
    }

    public static function rules(): array
    {
        return [
            'filled', 'string', 'max:' . self::MAX
        ];
    }

    private function validate(): void
    {
        //The bookmarks title can be the webPage url
        // because the default title is the webPage url
        //before it is updated or the webpage has no title tags in its raw html.
        if (Url::isValid($this->value)) {
            return;
        }

        if (mb_strlen($this->value) > self::MAX) {
            throw new \LengthException('Bookmark title cannot be greater ' . self::MAX);
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
