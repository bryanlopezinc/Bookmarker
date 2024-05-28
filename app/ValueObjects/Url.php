<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;
use Spatie\Url\QueryParameterBag;
use App\Exceptions\MalformedURLException;
use Stringable;

final class Url
{
    private readonly array $parts;
    private readonly string $url;

    public function __construct(string|Stringable $url)
    {
        $url = (string) $url;
        $parts = parse_url($url);

        if ( ! Str::isUrl($url, ['http', 'https'])) {
            throw MalformedURLException::invalidFormat($url);
        }

        $this->parts = $parts;
        $this->url = $url;
    }

    public static function isValid(mixed $url): bool
    {
        if ( ! is_string($url) && ! $url instanceof Stringable) {
            return false;
        }

        try {
            new self($url);
            return true;
        } catch (MalformedURLException) {
            return false;
        }
    }

    public function getHost(): string
    {
        return $this->parts['host'] ?? '';
    }

    public function getPath(): string
    {
        return $this->parts['path'] ?? '/';
    }

    /**
     * Parse the url query string  into an associative array.
     *
     * @return array<string,mixed>
     */
    public function parseQuery(): array
    {
        return QueryParameterBag::fromString($this->parts['query'] ?? '')->all();
    }

    public function toString(): string
    {
        return (string) $this->url;
    }
}
