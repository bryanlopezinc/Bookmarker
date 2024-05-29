<?php

declare(strict_types=1);

namespace App\Rules\PublicId;

use App\Contracts\ResourceNotFoundExceptionInterface;
use App\Exceptions\InvalidIdException;
use App\ValueObjects\PublicId\BookmarkSourceId;
use Exception;

final class BookmarkSourcePublicIdRule extends PublicIdRule
{
    protected function make(string $value): BookmarkSourceId
    {
        $exception = new class () extends Exception implements ResourceNotFoundExceptionInterface {
        };

        try {
            return BookmarkSourceId::fromRequest($value);
        } catch (InvalidIdException) {
            throw $exception;
        }
    }
}
