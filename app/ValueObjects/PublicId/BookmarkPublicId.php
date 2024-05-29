<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\BookmarkNotFoundException;
use App\Exceptions\InvalidIdException;

final class BookmarkPublicId extends PublicId
{
    public static function fromRequest(string $id): self
    {
        try {
            return parent::fromRequest($id);
        } catch (InvalidIdException) {
            throw new BookmarkNotFoundException();
        }
    }

    protected static function prefix(): IdPrefix
    {
        return IdPrefix::BOOKMARK;
    }
}
