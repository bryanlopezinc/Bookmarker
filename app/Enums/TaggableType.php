<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Taggable;

enum TaggableType: string
{
    case BOOKMARK = 'bookmark';

    public function type(): int
    {
        return match ($this) {
            self::BOOKMARK => Taggable::BOOKMARK_TYPE
        };
    }
}
