<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Url
{
    public function __construct(public readonly string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \DomainException('Invalid url ' . $value, 5050);
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
        } catch (\DomainException $e) {
            if ($e->getCode() !== 5050) {
                throw $e;
            }
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
