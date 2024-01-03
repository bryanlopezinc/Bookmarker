<?php

declare(strict_types=1);

namespace App\Import;

use App\Enums\ImportSource;

final class ImportBookmarkRequestData
{
    public function __construct(
        private string $importId,
        private ImportSource $importSource,
        private int $userID,
        private array $data
    ) {
    }

    public function userId(): int
    {
        return $this->userID;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function source(): ImportSource
    {
        return $this->importSource;
    }

    public function setSource(ImportSource $importSource): self
    {
        $this->importSource = $importSource;

        return $this;
    }

    public function importId(): string
    {
        return $this->importId;
    }

    public function getOption()
    {
        return new Option($this->data);
    }
}
