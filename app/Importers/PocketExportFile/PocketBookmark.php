<?php

declare(strict_types=1);

namespace App\Importers\PocketExportFile;

final class PocketBookmark
{
    public function __construct(
        public readonly string $url,
        public readonly string $timestamp,
        public readonly array $tags,
    ) {
    }
}
