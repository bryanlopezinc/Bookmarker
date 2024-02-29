<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\Bookmark;

interface BookmarkNotProcessedListenerInterface
{
    public function bookmarkNotProcessed(Bookmark $bookmark): void;
}
