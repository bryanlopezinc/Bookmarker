<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\FolderSettingValueInterface;

enum NewCollaboratorNotificationMode: string implements FolderSettingValueInterface
{
    case ALL           = '*';
    case INVITED_BY_ME = 'invitedByMe';

    public function notifyOnAllActivity(): bool
    {
        return $this == self::ALL;
    }

    public function notifyWhenCollaboratorWasInvitedByMe(): bool
    {
        return $this == self::INVITED_BY_ME;
    }
}