<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\Bookmark;

interface BookmarkNotProcessedListenerInterface
{
    public function bookmarkNotProcessed(Bookmark $bookmark): void;
}
