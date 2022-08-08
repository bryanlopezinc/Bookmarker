<?php

declare(strict_types=1);

namespace App\Utils;

use App\Contracts\UrlHasherInterface;
use App\HashedUrl;
use App\ValueObjects\Url;

final class UrlHasher implements UrlHasherInterface
{
    public function __construct(
        private readonly string $algo,
        private readonly array $options = []
    ) {
    }

    public function hashUrl(Url $url): HashedUrl
    {
        return (new HashedUrl)->make(
            hash($this->algo, $url->toString(), options: $this->options)
        );
    }
}
