<?php

declare(strict_types=1);

namespace App\Utils;

use App\HashedUrl;
use App\ValueObjects\Url;

class UrlHasher
{
    private const AlGO = 'xxh3';

    public function hashUrl(Url $url): HashedUrl
    {
        return new HashedUrl(
            hash(self::AlGO, $url->toString())
        );
    }
}
