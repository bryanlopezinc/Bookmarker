<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case FOLDER_UPDATED                = 'FolderUpdated';
    case BOOKMARKS_ADDED_TO_FOLDER     = 'bookmarksAddedToFolder';
    case BOOKMARKS_REMOVED_FROM_FOLDER = 'bookmarksRemovedFromFolder';
    case COLLABORATOR_EXIT             = 'collaboratorExitedFolder';
    case NEW_COLLABORATOR              = 'collaboratorAddedToFolder';
    case IMPORT_FAILED                 = 'ImportFailed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
