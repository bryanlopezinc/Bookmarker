<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;

abstract class EmailVerificationEvent
{
    public function __construct(private User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
