<?php

declare(strict_types=1);

namespace App\Collections;

use App\ValueObjects\PublicId\BookmarkPublicId;
use Illuminate\Support\Collection;

final class BookmarkPublicIdsCollection extends AbstractPublicIdsCollection
{
    public static function fromRequest(array $Ids): self
    {
        return collect($Ids)
            ->map(fn (string $Id) => BookmarkPublicId::fromRequest($Id))
            ->pipe(fn (Collection $collection) => new self($collection));
    }
}
