<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\ValueObjects\Tag;

final class InvalidTagException extends \Exception
{
    public const INVALID_MAX_LENGHT_CODE = 1900;
    public const EMPTY_TAG_CODE = 1901;
    public const APLHA_NUM_CODE = 1902;
}
