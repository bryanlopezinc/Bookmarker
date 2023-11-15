<?php

declare(strict_types=1);

namespace App\Utils;

use App\ValueObjects\Url;

class UrlHasher
{
    private const ALGO = 'xxh3';

    public function hashUrl(Url $url): string
    {
        return hash(self::ALGO, $url->toString());
    }
}
