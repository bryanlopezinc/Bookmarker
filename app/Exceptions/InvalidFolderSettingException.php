<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidFolderSettingException extends RuntimeException
{
    public function __construct(public readonly array $errorMessages)
    {
        parent::__construct(json_encode($errorMessages, JSON_THROW_ON_ERROR));
    }
}
