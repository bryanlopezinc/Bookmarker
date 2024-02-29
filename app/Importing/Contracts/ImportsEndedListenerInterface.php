<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\ImportBookmarksOutcome;

interface ImportsEndedListenerInterface
{
    public function importsEnded(ImportBookmarksOutcome $result): void;
}
