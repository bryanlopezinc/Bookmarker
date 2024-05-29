<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Enums\IdPrefix;

final class BookmarkSourceId extends PublicId
{
    protected static function prefix(): IdPrefix
    {
        return IdPrefix::BOOKMARK_SOURCE;
    }
}
