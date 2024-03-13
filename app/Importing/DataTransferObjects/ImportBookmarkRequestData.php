<?php

declare(strict_types=1);

namespace App\Importing\DataTransferObjects;

use App\Importing\Enums\ImportSource;
use App\Importing\Option;

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

    public function getOption(): Option
    {
        return new Option($this->data);
    }
}
