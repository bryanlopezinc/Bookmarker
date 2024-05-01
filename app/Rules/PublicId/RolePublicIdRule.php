<?php

declare(strict_types=1);

namespace App\Rules\PublicId;

use App\ValueObjects\PublicId\PublicId;
use App\ValueObjects\PublicId\RolePublicId;

final class RolePublicIdRule extends PublicIdRule
{
    protected function make(string $value): PublicId
    {
        return RolePublicId::fromRequest($value);
    }
}
