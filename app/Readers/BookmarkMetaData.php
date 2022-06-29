<?php

declare(strict_types=1);

namespace App\Readers;

use App\DataTransferObjects\DataTransferObject;
use App\ValueObjects\Url;

final class BookmarkMetaData extends DataTransferObject
{
    public readonly string|false $description;
    public readonly string|false $title;
    public readonly string|false $hostSiteName;
    public readonly Url|false $thumbnailUrl;

    /**
     * @param array<string,mixed> $data
     *
     * ```php
     *   $data = [
     *         'description' => string|false,
     *          'title' => string|false,
     *          'siteName' => string|false,
     *          'imageUrl' => App\ValueObjects\Url::class|false
     *    ]
     * ```
     */
    public static function fromArray(array $data): self
    {
        return new self([
            'description' => $data['description'],
            'title' => $data['title'],
            'hostSiteName' => $data['siteName'],
            'thumbnailUrl' => $data['imageUrl']
        ]);
    }
}
