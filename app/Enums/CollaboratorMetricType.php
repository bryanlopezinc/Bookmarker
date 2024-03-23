<?php

declare(strict_types=1);

namespace App\Enums;

enum CollaboratorMetricType: int
{
    case BOOKMARKS_ADDED     = 2;
    case BOOKMARKS_DELETED   = 3;
    case COLLABORATORS_ADDED = 4;
    case UPDATES             = 5;
}
