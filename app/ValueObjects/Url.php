<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\MalformedURLException;

final class Url
{
    private readonly array $parts;
    public readonly string $value;

    public function __construct(string|\Stringable $value)
    {
        $this->value = (string) $value;

        if (filter_var($this->value, FILTER_VALIDATE_URL) === false) {
            throw new MalformedURLException($value);
        }

        $this->parts = parse_url($value);
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

    public function getHostName(): string
    {
        return $this->parts['host'];
    }

    public function getPath(): string
    {
        return $this->parts['path'] ?? '';
    }

    /**
     * Parse the url query string  into an associative array.
     *
     * @return array<string,mixed>
     */
    public  function parseQuery(): array
    {
        $result = [];
        $queryString = $this->parts['query'] ?? '';

        if (blank($queryString)) {
            return $result;
        }

        foreach (explode('&', $queryString) as $query) {
            [$key, $value] = explode('=', $query);
            $result[$key] = $value;
        }

        return $result;
    }
}
