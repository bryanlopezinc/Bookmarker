<?php

declare(strict_types=1);

namespace App\Import\Contracts;

use App\Import\ReasonForSkippingBookmark;

interface BookmarkSkippedListenerInterface
{
    public function bookmarkSkipped(ReasonForSkippingBookmark $reason): void;
}
