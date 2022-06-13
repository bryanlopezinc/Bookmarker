<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class FolderBookmark
{
    public function __construct(public readonly Bookmark $bookmark, public readonly bool $isPublic)
    {
    }
}
