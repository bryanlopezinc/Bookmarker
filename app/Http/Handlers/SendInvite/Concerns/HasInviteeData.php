<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite\Concerns;

use App\Models\User;

trait HasInviteeData
{
    private User $invitee;

    public function setInvitee(User $invitee): void
    {
        $this->invitee = $invitee;
    }
}
