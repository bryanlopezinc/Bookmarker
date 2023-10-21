<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ImportSource;

final class ImportData
{
    public function __construct(
        private string $requestID,
        private ImportSource $importSource,
        private int $userID,
        private array $data
    ) {
    }

    public function userID(): int
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

    public function requestID(): string
    {
        return $this->requestID;
    }
}
