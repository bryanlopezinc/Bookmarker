<?php

declare(strict_types=1);

namespace App\Collections;

use App\ValueObjects\Tag;
use Illuminate\Support\Collection;

final class TagsCollection extends BaseCollection
{
    protected function isValid(mixed $item): bool
    {
        return $item instanceof Tag;
    }

    protected function validateItems(): void
    {
        parent::validateItems();

        $duplicates = $this->toStringCollection()->duplicatesStrict();

        if ($duplicates->isNotEmpty()) {
            throw new \LogicException('Collection contains duplicate tags ' . $duplicates->implode(','), 4500);
        }
    }

    /**
     * @param array<string> $tags
     */
    public static function createFromStrings(array $tags): self
    {
        return new self(array_map(fn (string $tag) => new Tag($tag), $tags));
    }

    /**
     * Get the string values from the tags objects as collection object.
     *
     * @return Collection<string>
     */
    public function toStringCollection(): Collection
    {
        return $this->collection->map(fn (Tag $tag): string => $tag->value);
    }

    /**
     * Get all the tags in the collection except the given tags
     */
    public function except(TagsCollection $tags): TagsCollection
    {
        return TagsCollection::createFromStrings(
            $this->toStringCollection()->diff($tags->toStringCollection())->values()->all()
        );
    }

    /**
     * Determine if the collection contains ANY of the given tags
     */
    public function contains(TagsCollection $tags): bool
    {
        return $this->toStringCollection()->intersect($tags->toStringCollection())->isNotEmpty();
    }
}
