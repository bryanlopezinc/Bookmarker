<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\Bookmark;

interface ImportStartedListenerInterface
{
    public function importStarted(Bookmark $bookmark): void;
}
