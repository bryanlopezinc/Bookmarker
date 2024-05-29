<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityType: int
{
    case BOOKMARKS_REMOVED                               = 1;
    case COLLABORATOR_EXIT                               = 2;
    case COLLABORATOR_REMOVED                            = 3;
    case DESCRIPTION_CHANGED                             = 4;
    case FOLDER_VISIBILITY_CHANGED_TO_COLLABORATORS_ONLY = 5;
    case FOLDER_VISIBILITY_CHANGED_TO_PUBLIC             = 6;
    case ICON_CHANGED                                    = 7;
    case NAME_CHANGED                                    = 8;
    case NEW_BOOKMARKS                                   = 9;
    case NEW_COLLABORATOR                                = 10;
    case SUSPENSION_LIFTED                               = 11;
    case USER_SUSPENDED                                  = 12;
    case DOMAIN_BLACKLISTED                              = 13;
    case DOMAIN_WHITELISTED                              = 14;
}
