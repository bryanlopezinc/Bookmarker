<?php

declare(strict_types=1);

namespace App\Enums;

enum UserBookmarksSortCriteria: string
{
    case OLDEST = 'asc';
    case NEWEST = 'desc';
}
