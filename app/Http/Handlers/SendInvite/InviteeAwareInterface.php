<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Models\User;

interface InviteeAwareInterface
{
    public function setInvitee(User $invitee): void;
}
