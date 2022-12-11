<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class MalformedURLException extends InvalidArgumentException
{
    public static function invalidFormat(string $url): self
    {
        return new self("The given url [$url] is invalid");
    }

    public static function invalidScheme(string $url, string $scheme): self
    {
        return new self("The given url scheme [$scheme] for url [$url] is invalid");
    }
}
