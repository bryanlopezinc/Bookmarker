<?php

declare(strict_types=1);

namespace App\Importing\DataTransferObjects;

use App\Importing\Collections\TagsCollection;

final class Bookmark
{
    public function __construct(
        public readonly string $url,
        public readonly TagsCollection $tags,
        public readonly int $lineNumber
    ) {
    }
}
