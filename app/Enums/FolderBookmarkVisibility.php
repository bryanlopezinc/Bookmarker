<?php

declare(strict_types=1);

namespace App\Enums;

enum FolderBookmarkVisibility: string
{
    case PUBLIC  = 'public';
    case PRIVATE = 'private';
}
