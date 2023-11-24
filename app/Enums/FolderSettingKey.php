<?php

declare(strict_types=1);

namespace App\Enums;

final class FolderSettingKey
{
    public const ENABLE_NOTIFICATIONS                                       = 'enable_notifications';
    public const NEW_COLLABORATOR_NOTIFICATION                              = 'notify_on_new_collaborator';
    public const ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION             = 'notify_on_new_collaborator_by_user';
    public const NOTIFy_ON_UPDATE                                           = 'notify_on_update';
    public const NOTIFY_ON_NEW_BOOKMARK                                     = 'notify_on_new_bookmark';
    public const NOTIFY_ON_BOOKMARK_DELETED                                 = 'notify_on_bookmark_delete';
    public const NOTIFY_ON_COLLABORATOR_EXIT                                = 'notify_on_collaborator_exit';
    public const NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION = 'notify_on_collaborator_exit_with_write';
}
