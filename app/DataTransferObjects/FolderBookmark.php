<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\FolderBookmarkVisibility;
use App\Models\Bookmark;

final class FolderBookmark
{
    public function __construct(
        public readonly Bookmark $bookmark,
        public readonly FolderBookmarkVisibility $visibility
    ) {
    }
}
