<?php

declare(strict_types=1);

namespace App\Enums;

enum Feature: string
{
    case ADD_BOOKMARKS    = 'ADD_BOOKMARKS';
    case DELETE_BOOKMARKS = 'DELETE_BOOKMARKS';
    case SEND_INVITES     = 'SEND_INVITES';
    case UPDATE_FOLDER    = 'UPDATE_FOLDER';
}
