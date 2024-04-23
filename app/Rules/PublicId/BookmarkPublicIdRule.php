<?php

declare(strict_types=1);

namespace App\Rules\PublicId;

use App\ValueObjects\PublicId\BookmarkPublicId;
use App\ValueObjects\PublicId\PublicId;

final class BookmarkPublicIdRule extends PublicIdRule
{
    protected function make(string $value): PublicId
    {
        return BookmarkPublicId::fromRequest($value);
    }
}
