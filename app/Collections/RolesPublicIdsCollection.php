<?php

declare(strict_types=1);

namespace App\Collections;

use App\ValueObjects\PublicId\RolePublicId;
use Illuminate\Support\Collection;

final class RolesPublicIdsCollection extends AbstractPublicIdsCollection
{
    public static function fromRequest(array $Ids): self
    {
        return collect($Ids)
            ->map(fn (string $Id) => RolePublicId::fromRequest($Id))
            ->pipe(fn (Collection $collection) => new self($collection));
    }
}
