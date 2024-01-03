<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\ImportBookmarkRequestData;

interface ImportsStartedListenerInterface
{
    public function importsStarted(ImportBookmarkRequestData $data): void;
}
