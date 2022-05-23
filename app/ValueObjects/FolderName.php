<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderName
{
    public const MAX = 50;

    public function __construct(public readonly string $value)
    {
        if (mb_strlen($value) > self::MAX) {
            throw new \Exception('Folder name cannot exceed ' . self::MAX);
        }
    }
}
