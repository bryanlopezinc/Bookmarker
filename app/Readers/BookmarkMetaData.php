<?php

declare(strict_types=1);

namespace App\Readers;

use App\ValueObjects\Url;

/**
 * @phpstan-type MetaData array{
 *   description: string|false,
 *   title: string|false,
 *   hostSiteName: string|false,
 *   iconUrl: \App\ValueObjects\Url|false,
 *   canonicalUrl: \App\ValueObjects\Url|false,
 *   resolvedUrl: \App\ValueObjects\Url,
 *  }
 */
final class BookmarkMetaData
{
    public function __construct(
        public readonly string|false $description,
        public readonly string|false $title,
        public readonly string|false $hostSiteName,
        public readonly Url|false $iconUrl,
        public readonly Url|false $canonicalUrl,
        public readonly Url $resolvedUrl
    ) {
    }

    /**
     * @phpstan-param MetaData $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
