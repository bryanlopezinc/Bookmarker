<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class MalformedURLException extends InvalidArgumentException
{
    public static function invalidFormat(string $url): self
    {
        return new self("The given url [{$url}] is invalid");
    }
}
