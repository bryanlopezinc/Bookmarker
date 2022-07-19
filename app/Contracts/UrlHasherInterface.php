<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\Url;

interface UrlHasherInterface
{
    public function hashUrl(Url $url): HashedUrlInterface;
}
