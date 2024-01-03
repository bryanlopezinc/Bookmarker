<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Import;

use App\Enums\ImportSource;
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
