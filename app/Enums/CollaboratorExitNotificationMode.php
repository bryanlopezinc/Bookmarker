<?php

declare(strict_types=1);

namespace App\Enums;

enum CollaboratorExitNotificationMode: string
{
    case ALL                  = '*';
    case HAS_WRITE_PERMISSION = 'hasWritePermission';

    public function notifyOnAllActivity(): bool
    {
        return $this == self::ALL;
    }

    public function notifyWhenCollaboratorHasWritePermission(): bool
    {
        return $this == self::HAS_WRITE_PERMISSION;
    }
}
