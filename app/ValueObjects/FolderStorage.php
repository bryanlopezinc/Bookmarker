<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderStorage
{
    public const MAX_ITEMS = 200;

    public function __construct(public readonly int $total)
    {
        if ($total  < 0) {
            throw new \InvalidArgumentException("Invalid item count $total");
        }

        if ($total > self::MAX_ITEMS) {
            throw new \InvalidArgumentException(
                sprintf('Folder cannot contain more than %s items %s given', self::MAX_ITEMS, $total)
            );
        }
    }

    public function spaceAvailable(): int
    {
        return self::MAX_ITEMS - $this->total;
    }

    public function isFull(): bool
    {
        return $this->spaceAvailable() === 0;
    }

    public function canContain(iterable $items): bool
    {
        if ($this->isFull()) {
            return false;
        }

        if (is_array($items)) {
            $items = new \ArrayIterator($items);
        }

        return $this->total + iterator_count($items) <= self::MAX_ITEMS;
    }

    public function percentageUsed(): int
    {
        $percentage = ($this->total / self::MAX_ITEMS) * 100;

        return (int)$percentage;
    }
}
