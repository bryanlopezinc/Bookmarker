<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DomainException;
use Illuminate\Support\Str;

final class InviteId
{
    public function __construct(public readonly string $value)
    {
        if ( ! Str::isUuid($value)) {
            throw new DomainException("invalid invite id [{$value}]");
        }
    }

    public static function generate(): InviteId
    {
        return new InviteId((string) Str::uuid());
    }
}
