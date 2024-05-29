<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\FolderNotFoundException;
use App\Exceptions\InvalidIdException;

final class FolderPublicId extends PublicId
{
    protected static function prefix(): IdPrefix
    {
        return IdPrefix::FOLDER;
    }

    public static function fromRequest(string $id): self
    {
        try {
            return parent::fromRequest($id);
        } catch (InvalidIdException) {
            throw new FolderNotFoundException();
        }
    }
}
