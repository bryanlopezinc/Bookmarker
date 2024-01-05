<?php

declare(strict_types=1);

namespace App\Readers;

use App\ValueObjects\Url;

final class BookmarkMetaData
{
    public readonly string|false $description;
    public readonly string|false $title;
    public readonly string|false $hostSiteName;
    public readonly Url|false $thumbnailUrl;
    public readonly Url|false $canonicalUrl;
    public readonly Url $resolvedUrl;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(protected array $attributes)
    {
        foreach ($this->attributes as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @param array{
     *   description: string|false,
     *   title: string|false,
     *   siteName: string|false,
     *   imageUrl: \App\ValueObjects\Url|false,
     *   canonicalUrl: \App\ValueObjects\Url|false,
     *   resolvedUrl: \App\ValueObjects\Url|false,
     *  } $data
     */
    public static function fromArray(array $data): self
    {
        return new self([
            'description'  => $data['description'],
            'title'        => $data['title'],
            'hostSiteName' => $data['siteName'],
            'thumbnailUrl' => $data['imageUrl'],
            'canonicalUrl' => $data['canonicalUrl'],
            'resolvedUrl'  => $data['resolvedUrl']
        ]);
    }
}
