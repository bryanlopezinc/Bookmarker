<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class MalformedURLException extends InvalidArgumentException
{
    public function __construct(string $url)
    {
        parent::__construct("The given url [$url] is invalid");
    }
}
