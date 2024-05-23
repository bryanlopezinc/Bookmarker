<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: int
{
    case FOLDER_NAME_UPDATED           = 2;
    case FOLDER_ICON_UPDATED           = 3;
    case FOLDER_DESCRIPTION_UPDATED    = 4;
    case BOOKMARKS_ADDED_TO_FOLDER     = 5;
    case BOOKMARKS_REMOVED_FROM_FOLDER = 6;
    case YOU_HAVE_BEEN_KICKED_OUT      = 7;
    case COLLABORATOR_REMOVED          = 8;
    case COLLABORATOR_EXIT             = 9;
    case NEW_COLLABORATOR              = 10;
    case IMPORT_FAILED                 = 11;
}
