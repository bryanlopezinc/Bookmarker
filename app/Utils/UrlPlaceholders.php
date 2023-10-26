<?php

declare(strict_types=1);

namespace App\Utils;

use Spatie\Url\QueryParameterBag;

final class UrlPlaceholders
{
    /**
     * @param array<string> $placeHolders
     *
     * @return array<string>
     */
    public static function missing(string $url, array $placeHolders): array
    {
        $parts = parse_url($url);

        $queryParametersAndPaths = array_merge(
            QueryParameterBag::fromString($parts['query'] ?? '')->all(),
            explode('/', $parts['path'] ?? '')
        );

        return array_diff($placeHolders, $queryParametersAndPaths);
    }
}
