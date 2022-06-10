<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderName
{
    public const MAX = 50;

    public function __construct(public readonly string $value)
    {
        if (blank($value)) {
            throw new \Exception('Folder name cannot be empty');
        }

        if (mb_strlen($value) > self::MAX) {
            throw new \Exception('Folder name cannot exceed ' . self::MAX);
        }
    }

    /**
     * Get the sanitized folderName.
     */
    public function safe(): string
    {
        return htmlspecialchars($this->value, ENT_QUOTES);
    }
}
