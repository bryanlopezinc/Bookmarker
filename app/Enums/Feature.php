<?php

declare(strict_types=1);

namespace App\Enums;

enum Feature: string
{
    case ADD_BOOKMARKS             = 'ADD_BOOKMARKS';
    case DELETE_BOOKMARKS          = 'DELETE_BOOKMARKS';
    case SEND_INVITES              = 'SEND_INVITES';
    case UPDATE_FOLDER             = 'UPDATE_FOLDER';
    case UPDATE_FOLDER_NAME        = 'UPDATE_FOLDER_NAME';
    case UPDATE_FOLDER_DESCRIPTION = 'UPDATE_FOLDER_DESCRIPTION';
    case JOIN_FOLDER               = 'JOIN_FOLDER';
    case REMOVE_USER               = 'REMOVE_USER';

    public static function publicIdentifiers(): array
    {
        return [
            self::ADD_BOOKMARKS->value             => 'addBookmarks',
            self::DELETE_BOOKMARKS->value          => 'removeBookmarks',
            self::SEND_INVITES->value              => 'inviteUsers',
            self::UPDATE_FOLDER->value             => 'updateFolder',
            self::REMOVE_USER->value               => 'removeUser',
            self::UPDATE_FOLDER_NAME->value        => 'updateFolderName',
            self::UPDATE_FOLDER_DESCRIPTION->value => 'updateFolderDescription'
        ];
    }
}
