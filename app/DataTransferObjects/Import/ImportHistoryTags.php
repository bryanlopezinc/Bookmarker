<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Import;

final class ImportHistoryTags
{
    public function __construct(private readonly array $tags)
    {
    }

    /**
     * @return array<string>
     */
    public function resolved(): array
    {
        return $this->tags['resolved'] ?? [];
    }

    /**
     * @return array<string>
     */
    public function invalid(): array
    {
        return $this->tags['invalid'] ?? [];
    }

    /**
     * @return array<string>
     */
    public function found(): int
    {
        return $this->tags['found'] ?? 0;
    }
}
