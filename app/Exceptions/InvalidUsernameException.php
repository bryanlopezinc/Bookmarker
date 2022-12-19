<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\ValueObjects\Username;

final class InvalidUsernameException extends \DomainException
{
    public static function lengthExceeded(): self
    {
        return new self('username cannot be greater than ' . Username::MAX_LENGTH, 5000);
    }

    public static function nameTooShort(): self
    {
        return new self('username must be greater than ' . Username::MIN_LENGTH, 5001);
    }

    public static function invalidCharacters(): self
    {
        return new self(
            'username can only contain lower case characters, upper case characters, numbers and underscores',
            5002
        );
    }
}
