<?php

declare(strict_types=1);

namespace App\Import;

enum ReasonForSkippingBookmark: int
{
    case INVALID_TAG        = 2;
    case TAG_MERGE_OVERFLOW = 3;
    case TAGS_TOO_LARGE     = 4;

    public function toWord(): string
    {
        return match ($this) {
            self::INVALID_TAG        => 'ContainsInvalidTag',
            self::TAG_MERGE_OVERFLOW => 'CannotMergeTags',
            self::TAGS_TOO_LARGE     => 'TagsLengthExceeded'
        };
    }
}
