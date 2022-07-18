<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\MalformedURLException;
use Illuminate\Support\Facades\Validator;
use Spatie\Url\QueryParameterBag;

final class Url
{
    private readonly array $parts;
    private readonly string $url;

    public function __construct(string|\Stringable $url)
    {
        $url = (string) $url;

        if (Validator::make(['value' => $url], ['value' => ['filled', 'url']])->fails()) {
            throw MalformedURLException::invalidFormat($url);
        }

        $this->parts = parse_url($url);
        $this->url = $url;
    }

    public static function isValid(mixed $url): bool
    {
        if (!is_string($url) && !$url instanceof \Stringable) {
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
    public  function parseQuery(): array
    {
        return QueryParameterBag::fromString($this->parts['query'] ?? '')->all();
    }

    private function getScheme(): string
    {
        return $this->parts['scheme'] ?? '';
    }

    public function isHttp(): bool
    {
        return $this->getScheme() === 'http';
    }

    public function isHttps(): bool
    {
        return $this->getScheme() === 'https';
    }

    public function toString(): string
    {
        return (string) $this->url;
    }
}
