<?php

declare(strict_types=1);

namespace App\Collections;

use App\Contracts\HasPublicIdInterface;
use App\ValueObjects\PublicId\PublicId;
use Illuminate\Support\Collection;

abstract class AbstractPublicIdsCollection
{
    protected function __construct(private readonly Collection $collection)
    {
    }

    /**
     * @param iterable<HasPublicIdInterface> $objects
     *
     * @return static
     */
    public static function fromObjects(iterable $objects)
    {
        return collect($objects)
            ->map(fn (HasPublicIdInterface $object) => $object->getPublicIdentifier())
            ->pipe(fn (Collection $collection) => new static($collection)); //@phpstan-ignore-line
    }

    public function values(): Collection
    {
        return $this->collection->map(fn (PublicId $Id) => $Id->value);
    }

    public function present(): Collection
    {
        return $this->collection->map(fn (PublicId $Id) => $Id->present());
    }
}
