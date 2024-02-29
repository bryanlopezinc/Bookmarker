<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\ImportBookmarkRequestData;

interface ImportsStartedListenerInterface
{
    public function importsStarted(ImportBookmarkRequestData $data): void;
}
