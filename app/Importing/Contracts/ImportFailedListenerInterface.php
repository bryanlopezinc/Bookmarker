<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\Bookmark;
use App\Importing\Enums\ImportBookmarksStatus;

interface ImportFailedListenerInterface
{
    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void;
}
