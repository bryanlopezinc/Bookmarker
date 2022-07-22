<?php

declare(strict_types=1);

namespace App\Importers\Pocket;

final class Bookmark
{
    public function __construct(
        public readonly string $url,
        public readonly string $timestamp,
        public readonly array $tags,
    ) {
    }
}
