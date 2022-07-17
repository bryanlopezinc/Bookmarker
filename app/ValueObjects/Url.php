<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\MalformedURLException;
use Spatie\Url\Url as SpatieUrl;
use Spatie\Url\Exceptions\InvalidArgument;

final class Url
{
    private readonly SpatieUrl $url;

    public function __construct(string|\Stringable $value)
    {
        $exception = new MalformedURLException((string)$value);

        try {
            $this->url = SpatieUrl::fromString((string) $value);

            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                throw $exception;
            }
        } catch (InvalidArgument) {
            throw $exception;
        }
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
        return $this->url->getHost();
    }

    public function getPath(): string
    {
        return $this->url->getPath();
    }

    /**
     * Parse the url query string  into an associative array.
     *
     * @return array<string,mixed>
     */
    public  function parseQuery(): array
    {
        return $this->url->getAllQueryParameters();
    }

    public function toString(): string
    {
        return (string) $this->url;
    }
}
