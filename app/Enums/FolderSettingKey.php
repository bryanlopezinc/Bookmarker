<?php

declare(strict_types=1);

namespace App\Enums;

enum FolderSettingKey: string
{
    case ENABLE_NOTIFICATIONS                                       = 'enable_notifications';
    case NEW_COLLABORATOR_NOTIFICATION                              = 'notify_on_new_collaborator';
    case ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION             = 'notify_on_new_collaborator_by_user';
    case NOTIFy_ON_UPDATE                                           = 'notify_on_update';
    case NOTIFY_ON_NEW_BOOKMARK                                     = 'notify_on_new_bookmark';
    case NOTIFY_ON_BOOKMARK_DELETED                                 = 'notify_on_bookmark_delete';
    case NOTIFY_ON_COLLABORATOR_EXIT                                = 'notify_on_collaborator_exit';
    case NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION = 'notify_on_collaborator_exit_with_write';
}
