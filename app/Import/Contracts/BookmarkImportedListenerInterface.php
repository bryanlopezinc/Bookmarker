<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\Bookmark;

interface BookmarkImportedListenerInterface
{
    public function bookmarkImported(Bookmark $bookmark): void;
}
