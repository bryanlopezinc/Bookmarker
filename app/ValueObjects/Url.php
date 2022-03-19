<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidUrlException;

final class Url
{
    public function __construct(public readonly string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidUrlException('Invalid url ' . $value);
        }
    }

    /**
     * Create a new url object or return false if the url is invalid
     */
    public static function tryFromString(?string $url): self|false
    {
        if (blank($url)) return false;

        try {
            return new self($url);
        } catch (InvalidUrlException) {
            return false;
        }
    }

    public static function isValid(string $url): bool
    {
        return static::tryFromString($url) !== false;
    }

    public function getHostName(): string
    {
        return parse_url($this->value, PHP_URL_HOST);
    }
}
