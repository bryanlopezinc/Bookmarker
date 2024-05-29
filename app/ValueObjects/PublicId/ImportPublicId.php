<?php

declare(strict_types=1);

namespace App\ValueObjects\PublicId;

use App\Enums\IdPrefix;
use App\Exceptions\HttpException;
use App\Exceptions\InvalidIdException;

final class ImportPublicId extends PublicId
{
    public static function fromRequest(string $id): self
    {
        try {
            return parent::fromRequest($id);
        } catch (InvalidIdException) {
            throw HttpException::notFound(['message' => 'RecordNotFound']);
        }
    }

    protected static function prefix(): IdPrefix
    {
        return IdPrefix::IMPORT;
    }
}
