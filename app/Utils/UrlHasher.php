<?php

declare(strict_types=1);

namespace App\Utils;

use App\HashedUrl;
use App\ValueObjects\Url;

class UrlHasher
{
    private const ALGO = 'xxh3';

    public function hashUrl(Url $url): HashedUrl
    {
        return new HashedUrl(
            hash(self::ALGO, $url->toString())
        );
    }
}
