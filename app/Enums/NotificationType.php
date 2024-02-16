<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case FOLDER_UPDATED                = 'FolderUpdated';
    case BOOKMARKS_ADDED_TO_FOLDER     = 'BookmarksAddedToFolder';
    case BOOKMARKS_REMOVED_FROM_FOLDER = 'BookmarksRemovedFromFolder';
    case YOU_HAVE_BEEN_KICKED_OUT      = 'YouHaveBeenKickedOut';
    case COLLABORATOR_EXIT             = 'CollaboratorExitedFolder';
    case NEW_COLLABORATOR              = 'CollaboratorAddedToFolder';
    case IMPORT_FAILED                 = 'ImportFailed';
}
