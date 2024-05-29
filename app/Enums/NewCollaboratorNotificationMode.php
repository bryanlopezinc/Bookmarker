<?php

declare(strict_types=1);

namespace App\Enums;

enum NewCollaboratorNotificationMode: string
{
    case ALL           = '*';
    case INVITED_BY_ME = 'invitedByMe';
}
