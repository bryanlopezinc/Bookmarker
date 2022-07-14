<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ImportSource;
use App\ValueObjects\UserID;
use App\ValueObjects\Uuid;

final class ImportData
{
    public function __construct(
        private  Uuid $requestID,
        private  ImportSource $importSource,
        private UserID $userID,
        private  array $data
    ) {
    }

    public function userID(): UserID
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

    public function requestID(): Uuid
    {
        return $this->requestID;
    }
}
