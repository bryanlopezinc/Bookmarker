<?php

declare(strict_types=1);

namespace App;

use App\Contracts\UrlHasherInterface;
use App\ValueObjects\Url;

final class UrlHasher implements UrlHasherInterface
{
    public function __construct(
        private readonly string $algo,
        private readonly array $options = []
    ) {
    }

    public function hashCanonicalUrl(Url $url): HashedUrl
    {
        return (new HashedUrl)->make(
            hash($this->algo, $url->value, options: $this->options)
        );
    }
}
