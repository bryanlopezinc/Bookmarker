<?php

declare(strict_types=1);

namespace App\Importing\DataTransferObjects;

use App\Importing\Enums\ImportSource;
use App\ValueObjects\Url;
use Carbon\Carbon;

final class ImportedBookmark
{
    public function __construct(
        public readonly Url $url,
        public readonly Carbon $createdOn,
        public readonly int $userId,
        public readonly array $tags,
        public readonly ImportSource $importSource
    ) {
    }
}
