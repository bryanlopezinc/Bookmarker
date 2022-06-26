<?php

declare(strict_types=1);

namespace App\Events;

use App\DataTransferObjects\User;
use App\ValueObjects\Url;

abstract class EmailVerificationEvent
{
    public function __construct(private User $user, private Url $verificationUrl)
    {
    }

    public function getVerificationUrl(): Url
    {
        return $this->verificationUrl;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
