<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Taggable;

enum TaggableType
{
    case BOOKMARK;
    case FOLDER;

    public function type(): int
    {
        return match ($this) {
            self::BOOKMARK => Taggable::BOOKMARK_TYPE,
            self::FOLDER => Taggable::FOLDER_TYPE
        };
    }
}
