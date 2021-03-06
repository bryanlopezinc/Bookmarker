<?php

declare(strict_types=1);

namespace App\Collections;

use App\ValueObjects\ResourceID;
use Illuminate\Support\Collection;

final class ResourceIDsCollection extends BaseCollection
{
    protected function isValid(mixed $item): bool
    {
        return $item instanceof ResourceID;
    }

    /**
     * @param iterable<int> $ids
     */
    public static function fromNativeTypes(iterable $ids): self
    {
        return new ResourceIDsCollection(
            collect($ids)->map(fn (int $id) => new ResourceID($id))
        );
    }

    /**
     * @return Collection<int>
     */
    public function asIntegers(): Collection
    {
        return $this->collection->map(fn (ResourceID $resourceID) => $resourceID->toInt());
    }
}
