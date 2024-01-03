<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\ImportBookmarksOutcome;

interface ImportsEndedListenerInterface
{
    public function importsEnded(ImportBookmarksOutcome $result): void;
}
