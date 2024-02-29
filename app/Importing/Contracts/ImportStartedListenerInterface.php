<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\Bookmark;

interface ImportStartedListenerInterface
{
    public function importStarted(Bookmark $bookmark): void;
}
