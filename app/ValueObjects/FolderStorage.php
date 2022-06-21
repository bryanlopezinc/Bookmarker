<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderStorage
{
    public const MAX = 200;

    public function __construct(public readonly int $total)
    {
        new PositiveNumber($total);

        if ($total > self::MAX) {
            throw new \InvalidArgumentException(
                sprintf('Folder cannot contain more than %s items %s given', self::MAX, $total)
            );
        }
    }

    public function spaceAvailable(): int
    {
        return self::MAX - $this->total;
    }

    public function isFull(): bool
    {
        return $this->spaceAvailable() === 0;
    }

    public function canContain(iterable $items): bool
    {
        return $this->total + count($items) <= self::MAX;
    }

    public function percentageUsed(): int
    {
        $percentage = ($this->total / self::MAX) * 100;

        return (int)$percentage;
    }
}
