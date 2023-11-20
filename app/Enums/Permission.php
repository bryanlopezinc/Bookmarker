<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case VIEW_BOOKMARKS   = 'VIEW_BOOKMARKS';
    case ADD_BOOKMARKS    = 'ADD_BOOKMARKS';
    case DELETE_BOOKMARKS = 'DELETE_BOOKMARKS';
    case INVITE_USER      = 'INVITE_USER';
    case UPDATE_FOLDER    = 'UPDATE_FOLDER';
}
