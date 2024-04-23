<?php

declare(strict_types=1);

namespace App\Importing\DataTransferObjects;

use App\Importing\Option;
use Illuminate\Support\Str;
use App\Importing\Enums\ImportSource;

final class ImportBookmarkRequestData
{
    public function __construct(
        private int $importId,
        private ImportSource $importSource,
        private int $userID,
        private array $data,
        private string $importFileName
    ) {
        assert(Str::isUuid($importFileName));
    }

    public function getFileName(): string
    {
        return $this->importFileName;
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

    public function importId(): int
    {
        return $this->importId;
    }

    public function getOption(): Option
    {
        return new Option($this->data);
    }
}
