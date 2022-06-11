<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderDescription
{
    public const MAX = 150;

    public function __construct(public readonly ?string $value)
    {
        if ($value === null) {
            return;
        }

        if (mb_strlen($value) > self::MAX) {
            throw new \Exception('Folder description cannot exceed ' . self::MAX);
        }
    }

    public function isEmpty(): bool
    {
        return blank($this->value);
    }

    /**
     * Get the sanitized folderDescription.
     */
    public function safe(): string
    {
        return htmlspecialchars($this->value, ENT_QUOTES);
    }
}
