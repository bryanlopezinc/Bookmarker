<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\Bookmark;
use App\Import\ImportBookmarksStatus;

interface ImportFailedListenerInterface
{
    public function importFailed(Bookmark $bookmark, ImportBookmarksStatus $reason): void;
}
