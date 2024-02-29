<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\DataTransferObjects\Bookmark;

interface ImportEndedListenerInterface
{
    public function bookmarkImported(Bookmark $bookmark): void;
}
