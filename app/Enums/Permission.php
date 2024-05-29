<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case ADD_BOOKMARKS             = 'ADD_BOOKMARKS';
    case DELETE_BOOKMARKS          = 'DELETE_BOOKMARKS';
    case INVITE_USER               = 'INVITE_USER';
    case UPDATE_FOLDER_NAME        = 'UPDATE_FOLDER_NAME';
    case UPDATE_FOLDER_DESCRIPTION = 'UPDATE_FOLDER_DESCRIPTION';
    case UPDATE_FOLDER_ICON        = 'UPDATE_FOLDER_ICON';
    case REMOVE_USER               = 'REMOVE_USER';
    case SUSPEND_USER              = 'SUSPEND_USER';
    case BLACKLIST_DOMAIN          = 'BLACKLIST_DOMAIN';
    case WHITELIST_DOMAIN          = 'WHITELIST_DOMAIN';

    /**
     * @return array<Permission>
     */
    public static function updateFolderTypes(): array
    {
        return [
            self::UPDATE_FOLDER_DESCRIPTION,
            self::UPDATE_FOLDER_NAME,
            self::UPDATE_FOLDER_ICON
        ];
    }
}
