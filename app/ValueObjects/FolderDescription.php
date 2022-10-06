<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class FolderDescription
{
    public const MAX_LENGTH = 150;

    public function __construct(public readonly ?string $value)
    {
        if ($value === null) {
            return;
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new \LengthException('Folder description cannot exceed ' . self::MAX_LENGTH);
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
        if ($this->isEmpty()) {
            return '';
        }

        //already null checked
        // @phpstan-ignore-next-line
        return htmlspecialchars($this->value, ENT_QUOTES);
    }
}
