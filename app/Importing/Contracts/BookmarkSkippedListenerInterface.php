<?php

declare(strict_types=1);

namespace App\Importing\Contracts;

use App\Importing\Enums\ReasonForSkippingBookmark;

interface BookmarkSkippedListenerInterface
{
    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void;
}
