<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\InvalidIdException;
use App\Exceptions\UserNotFoundException;

final class UserPublicId extends PublicId
{
    public static function fromRequest(string $id): self
    {
        try {
            return parent::fromRequest($id);
        } catch (InvalidIdException) {
            throw new UserNotFoundException();
        }
    }

    protected static function prefix(): IdPrefix
    {
        return IdPrefix::USER;
    }
}
