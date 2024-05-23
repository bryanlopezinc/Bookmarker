<?php

declare(strict_types=1);

namespace App\Enums;

enum CollaboratorExitNotificationMode: string
{
    case ALL                  = '*';
    case HAS_WRITE_PERMISSION = 'hasWritePermission';
}
