<?php

declare(strict_types=1);

namespace App\Enums;

enum Feature: string
{
    case ADD_BOOKMARKS             = 'ADD_BOOKMARKS';
    case DELETE_BOOKMARKS          = 'DELETE_BOOKMARKS';
    case SEND_INVITES              = 'SEND_INVITES';
    case UPDATE_FOLDER_NAME        = 'UPDATE_FOLDER_NAME';
    case UPDATE_FOLDER_DESCRIPTION = 'UPDATE_FOLDER_DESCRIPTION';
    case UPDATE_FOLDER_ICON        = 'UPDATE_FOLDER_ICON';
    case JOIN_FOLDER               = 'JOIN_FOLDER';
    case REMOVE_USER               = 'REMOVE_USER';
    case SUSPEND_USER              = 'SUSPEND_USER';

    public static function publicIdentifiers(): array
    {
        $ids = [
            self::ADD_BOOKMARKS->value             => 'addBookmarks',
            self::DELETE_BOOKMARKS->value          => 'removeBookmarks',
            self::SEND_INVITES->value              => 'inviteUsers',
            self::REMOVE_USER->value               => 'removeUser',
            self::UPDATE_FOLDER_NAME->value        => 'updateFolderName',
            self::UPDATE_FOLDER_DESCRIPTION->value => 'updateFolderDescription',
            self::UPDATE_FOLDER_ICON->value        => 'updateFolderIcon',
            self::JOIN_FOLDER->value               => 'joinFolder',
            self::SUSPEND_USER->value              => 'suspendUser'
        ];

        assert(count($ids) === count(self::cases()));

        return $ids;
    }

    public static function fromPublicId(string $value): static
    {
        return self::from(
            array_flip(self::publicIdentifiers())[$value]
        );
    }

    public function present(): string
    {
        return self::publicIdentifiers()[$this->value];
    }
}
