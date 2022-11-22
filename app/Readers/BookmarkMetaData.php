<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\Constructor;
use App\DataTransferObjects\DataTransferObject;
use App\ValueObjects\Url;

final class BookmarkMetaData extends DataTransferObject
{
    use Constructor;

    public readonly string|false $description;
    public readonly string|false $title;
    public readonly string|false $hostSiteName;
    public readonly Url|false $thumbnailUrl;
    public readonly Url|false $canonicalUrl;
    public readonly Url $resolvedUrl;

    /**
     * @param array<string,mixed> $data
     *
     * ```php
     *   $data = [
     *         'description' => string|false,
     *          'title' => string|false,
     *          'siteName' => string|false,
     *          'imageUrl' => App\ValueObjects\Url::class|false,
     *          'canonicalUrl' => App\ValueObjects\Url::class|false,
     *          'resolvedUrl' => App\ValueObjects\Url::class
     *    ]
     * ```
     */
    public static function fromArray(array $data): self
    {
        return new self([
            'description' => $data['description'],
            'title' => $data['title'],
            'hostSiteName' => $data['siteName'],
            'thumbnailUrl' => $data['imageUrl'],
            'canonicalUrl' => $data['canonicalUrl'],
            'resolvedUrl' => $data['resolvedUrl']
        ]);
    }
}
