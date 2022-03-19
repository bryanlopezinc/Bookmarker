<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\ValueObjects\Username;

final class InvalidUsernameException extends \RuntimeException
{
    public static function dueToLengthExceeded(): self
    {
        return new self('username cannot be greater than ' . Username::MAX, 5000);
    }

    public static function dueToNameTooShort(): self
    {
        return new self('username must be greater than ' . Username::MIN, 5001);
    }

    public static function dueToInvalidCharacters(): self
    {
        return new self('username can only contain lower case characters, upper case characters, numbers and underscores', 5002);
    }
}
